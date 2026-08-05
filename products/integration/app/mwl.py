"""MWL SCP — Modality Worklist server untuk RIS (C-FIND).

Modality query jadwal via DICOM C-FIND ke SCP ini; data dibaca dari
RIS /api/worklist. Transformasi RIS JSON → DICOM dataset.
"""
from __future__ import annotations

import logging
import os
from datetime import datetime

import httpx
from pydicom.dataset import Dataset
from pynetdicom import AE, evt
from pynetdicom.sop_class import (
    ModalityWorklistInformationFind as MWL_FIND,
)

log = logging.getLogger(__name__)

RIS_WORKLIST_URL = os.getenv("RIS_WORKLIST_URL", "https://ris.ddev.site/api/worklist")
AET = os.getenv("MWL_AET", "MWL_SCP")
PORT = int(os.getenv("MWL_PORT", "4243"))


def fetch_worklist() -> list[dict]:
    r = httpx.get(RIS_WORKLIST_URL, verify=False, timeout=15)
    r.raise_for_status()
    return r.json()


def _fmt_dt(value: str | None) -> tuple[str, str]:
    """'2026-08-05 10:30:00' → (date, time) DICOM, atau ('', '')."""
    if not value:
        return "", ""
    try:
        dt = datetime.strptime(value, "%Y-%m-%d %H:%M:%S")
    except ValueError:
        return "", ""
    return dt.strftime("%Y%m%d"), dt.strftime("%H%M%S")


def to_dataset(item: dict) -> Dataset:
    """Worklist RIS → DICOM MWL dataset."""
    order = item.get("order", {})
    patient = order.get("patient", {})
    date, time = _fmt_dt(item.get("scheduled_at"))
    ds = Dataset()
    ds.SpecificCharacterSet = "ISO_IR 192"
    ds.PatientID = patient.get("patient_id", "")
    ds.PatientName = patient.get("name", "")
    ds.AccessionNumber = order.get("order_no", "")
    ds.Modality = order.get("modality", "")
    ds.RequestedProcedureID = order.get("order_no", "")
    ds.ScheduledProcedureStepSequence = [Dataset()]
    step = ds.ScheduledProcedureStepSequence[0]
    step.ScheduledStationAETitle = item.get("scheduled_aet", "RIS")
    step.ScheduledProcedureStepStartDate = date
    step.ScheduledProcedureStepStartTime = time
    step.ScheduledProcedureStepID = str(item.get("order_id", ""))
    step.Modality = ds.Modality
    return ds


def _query_value(query: Dataset, root_key: str, seq_key: str | None = None):
    """Baca nilai pencarian dari root atau item ScheduledProcedureStepSequence."""
    if hasattr(query, root_key) and str(getattr(query, root_key)):
        return str(getattr(query, root_key))
    if seq_key and hasattr(query, "ScheduledProcedureStepSequence") and query.ScheduledProcedureStepSequence:
        item = query.ScheduledProcedureStepSequence[0]
        if hasattr(item, seq_key) and str(getattr(item, seq_key)):
            return str(getattr(item, seq_key))
    return ""


def matches(query: Dataset, item_ds: Dataset) -> bool:
    """Kecocokan key pencarian C-FIND (nilai kosong = wildcard)."""
    step = item_ds.ScheduledProcedureStepSequence[0] if item_ds.ScheduledProcedureStepSequence else item_ds
    checks = [
        ("Modality", "Modality", "Modality"),                      # root/spss
        ("PatientID", "PatientID", None),                          # root
        ("ScheduledStationAETitle", "ScheduledStationAETitle", "ScheduledStationAETitle"),  # spss
    ]
    for root_key, ds_key, seq_key in checks:
        qv = _query_value(query, root_key, seq_key)
        if not qv:
            continue
        actual = str(getattr(item_ds, ds_key, "")) if seq_key is None else str(getattr(step, ds_key, ""))
        if qv != actual:
            return False
    return True


def on_c_find(event):
    """C-FIND handler: yield (Pending, identifier) per item worklist RIS."""
    try:
        items = fetch_worklist()
    except Exception as e:  # RIS turun → tanpa match (Success)
        log.error("RIS worklist gagal: %s", e)
        return
    for item in items:
        if item.get("status", "pending") == "cancelled":
            continue
        ds = to_dataset(item)
        if matches(event.identifier, ds):
            yield (0xFF00, ds)


def start(port: int = PORT, ae_title: str = AET) -> AE:
    """Jalankan SCP (blocking) — panggil di thread bila perlu."""
    handlers = [(evt.EVT_C_FIND, on_c_find)]
    ae = AE(ae_title=ae_title)
    ae.add_supported_context(MWL_FIND)
    ae.start_server(("0.0.0.0", port), evt_handlers=handlers)
    return ae
