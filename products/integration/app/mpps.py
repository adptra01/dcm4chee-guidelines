"""MPPS SCP — Modality Performed Procedure Step.

Modality kirim N-CREATE (in progress) / N-SET (completed) → update status
order di RIS. Data matching via AccessionNumber (order_no) atau
ScheduledProcedureStepSequence.
"""
from __future__ import annotations

import logging
import os

import httpx
from pydicom.dataset import Dataset
from pynetdicom import AE, evt
from pynetdicom.sop_class import ModalityPerformedProcedureStep

log = logging.getLogger(__name__)

RIS_URL = os.getenv("RIS_URL", "https://ris.ddev.site/api")
RIS_VERIFY = os.getenv("RIS_VERIFY", "false").lower() == "true"
AET = os.getenv("MPPS_AET", "MPPS_SCP")
PORT = int(os.getenv("MPPS_PORT", "4244"))


def find_order_id(accession: str) -> int | None:
    """Cari order RIS by AccessionNumber (order_no) — scan index order."""
    r = httpx.get(f"{RIS_URL}/orders", verify=RIS_VERIFY, timeout=15)
    for o in r.json():
        if o["order_no"] == accession:
            return o["id"]
    return None


def update_order_status(order_id: int, status: str) -> bool:
    r = httpx.patch(f"{RIS_URL}/orders/{order_id}/status",
                    json={"status": status}, verify=RIS_VERIFY, timeout=15)
    return r.status_code in (200, 201)


def _extract_accession(ds: Dataset) -> str:
    """AccessionNumber dari N-CREATE/N-SET dataset."""
    if hasattr(ds, "ScheduledProcedureStepSequence") and ds.ScheduledProcedureStepSequence:
        step = ds.ScheduledProcedureStepSequence[0]
        if hasattr(step, "AccessionNumber"):
            return str(step.AccessionNumber)
    return str(getattr(ds, "AccessionNumber", ""))


def _handler_ok():
    """Return tuple (Success, None) — format pynetdicom 3.x N-CREATE/N-SET."""
    return (0x0000, None)


def on_n_create(event):
    """N-CREATE: modality mulai prosedur → order in_progress."""
    try:
        accession = _extract_accession(event.attribute_list)
        oid = find_order_id(accession)
        if oid is None:
            log.warning("MPPS N-CREATE: order %s tak ditemukan", accession)
            return (0x0117, None)  # NoSuchObjectInstance
        update_order_status(oid, "in_progress")
        return _handler_ok()
    except Exception as e:
        log.error("MPPS N-CREATE gagal: %s", e)
        return (0x0110, None)


def on_n_set(event):
    """N-SET: selesai → status dari PerformedProcedureStepStatus."""
    try:
        accession = _extract_accession(event.attribute_list)
        oid = find_order_id(accession)
        if oid is None:
            log.warning("MPPS N-SET: order %s tak ditemukan", accession)
            return (0x0117, None)
        status = str(getattr(event.attribute_list, "PerformedProcedureStepStatus", "COMPLETED"))
        ris_status = "completed" if status in ("COMPLETED", "DISCONTINUED") else "in_progress"
        update_order_status(oid, ris_status)
        return _handler_ok()
    except Exception as e:
        log.error("MPPS N-SET gagal: %s", e)
        return (0x0110, None)


def start(port: int = PORT, ae_title: str = AET) -> AE:
    ae = AE(ae_title=ae_title)
    ae.add_supported_context(ModalityPerformedProcedureStep)
    handlers = [
        (evt.EVT_N_CREATE, on_n_create),
        (evt.EVT_N_SET, on_n_set),
    ]
    ae.start_server(("0.0.0.0", port), evt_handlers=handlers)
    return ae
