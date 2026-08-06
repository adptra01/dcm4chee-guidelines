"""dicom-core: parser metadata, preview PNG, C-ECHO / C-STORE (SCU).

MS1 — satu-satunya tempat logika DICOM inti yang dipakai produk lain
(omc/api, integration). Tidak boleh depend ke produk mana pun.

Fungsi publik:
  parse(path)                      -> dict metadata
  preview(path, wc=None, ww=None)  -> bytes PNG
  echo(host, port, ...)            -> bool
  store(path, host, port, ...)     -> pynetdicom status
"""
from __future__ import annotations

import io

import pydicom
from pydicom import dcmread
from pydicom.dataset import Dataset
from pynetdicom import AE
from pynetdicom.sop_class import (
    DigitalXRayImageStorageForPresentation,
    ModalityPerformedProcedureStep as MPPS,
    ModalityWorklistInformationFind as MWL_FIND,
    Verification,
)

DEFAULT_SCU_AE = "ORP_CORE"
DEFAULT_SCP_AE = "ORTHANC"
DEFAULT_PORT = 4242


# ---------------------------------------------------------------- parser
def parse(path: str) -> dict:
    """Metadata DICOM yang relevan, sebagai dict JSON-safe."""
    ds = dcmread(path, stop_before_pixels=True)
    return {
        "SOPClassUID": str(ds.SOPClassUID),
        "SOPInstanceUID": str(ds.SOPInstanceUID),
        "StudyInstanceUID": str(getattr(ds, "StudyInstanceUID", "")),
        "SeriesInstanceUID": str(getattr(ds, "SeriesInstanceUID", "")),
        "Modality": str(getattr(ds, "Modality", "")),
        "PatientName": str(getattr(ds, "PatientName", "")),
        "PatientID": str(getattr(ds, "PatientID", "")),
        "StudyDate": str(getattr(ds, "StudyDate", "")),
        "StudyDescription": str(getattr(ds, "StudyDescription", "")),
        "SeriesDescription": str(getattr(ds, "SeriesDescription", "")),
        "Rows": int(getattr(ds, "Rows", 0)),
        "Columns": int(getattr(ds, "Columns", 0)),
        "BitsAllocated": int(getattr(ds, "BitsAllocated", 0)),
        "PhotometricInterpretation": str(getattr(ds, "PhotometricInterpretation", "")),
        "RescaleSlope": float(getattr(ds, "RescaleSlope", 1.0)),
        "RescaleIntercept": float(getattr(ds, "RescaleIntercept", 0.0)),
    }


# --------------------------------------------------------------- preview
def _apply_window(arr, wc, ww):
    """Terapkan window/level (Center/Width) → array uint8 0-255."""
    import numpy as np

    lo = wc - ww / 2.0
    hi = wc + ww / 2.0
    out = np.clip(arr, lo, hi)
    return ((out - lo) / (hi - lo) * 255.0).astype("uint8")


def preview(path: str, wc: float | None = None, ww: float | None = None) -> bytes:
    """Pixel data → bytes PNG (8-bit grayscale, windowed, MONOCHROME1 di-invert).

    W/C default dari tag file; fallback mean / full-range bila tag tak ada.
    """
    from PIL import Image

    ds = dcmread(path)
    arr = ds.pixel_array
    if wc is None:
        wc = float(ds.WindowCenter if hasattr(ds, "WindowCenter") else arr.mean())
    if ww is None:
        ww = float(ds.WindowWidth if hasattr(ds, "WindowWidth") else (arr.max() - arr.min()))

    img8 = _apply_window(arr, wc, ww)
    if ds.PhotometricInterpretation == "MONOCHROME1":
        img8 = 255 - img8

    buf = io.BytesIO()
    Image.fromarray(img8, mode="L").save(buf, format="PNG")
    return buf.getvalue()


# ------------------------------------------------------------------ scu
def _associate(host: str, port: int, scu_ae: str, scp_ae: str):
    ae = AE(ae_title=scu_ae)
    ae.add_requested_context(Verification)
    ae.add_requested_context(DigitalXRayImageStorageForPresentation)
    return ae.associate(host, port, ae_title=scp_ae)


def echo(host: str = "localhost", port: int = DEFAULT_PORT,
         scu_ae: str = DEFAULT_SCU_AE, scp_ae: str = DEFAULT_SCP_AE) -> bool:
    """C-ECHO (Verification) — True bila status Success."""
    assoc = _associate(host, port, scu_ae, scp_ae)
    if not assoc.is_established:
        return False
    try:
        status = assoc.send_c_echo()
        return status and status.Status == 0x0000
    finally:
        assoc.release()


def store(path: str, host: str = "localhost", port: int = DEFAULT_PORT,
          scu_ae: str = DEFAULT_SCU_AE, scp_ae: str = DEFAULT_SCP_AE) -> int | None:
    """C-STORE satu file — kembalikan Status int, None bila gagal/Error."""
    assoc = _associate(host, port, scu_ae, scp_ae)
    if not assoc.is_established:
        return None
    try:
        ds = dcmread(path)
        status = assoc.send_c_store(ds)
        return status.Status if status else None
    finally:
        assoc.release()


def mwl_query(model: Dataset, host: str = "localhost", port: int = 4243,
              scu_ae: str = "OMC_CONSOLE", scp_ae: str = "MWL_SCP") -> list[Dataset]:
    """C-FIND MWL (SCU) ke Integration :4243 — kembalikan daftar jadwal.

    pynetdicom 3.x: send_c_find(dataset, query_model) — abstract syntax
    ditentukan oleh query_model (UID MWL), bukan parameter terpisah.
    """
    assoc = _associate_mwl(host, port, scu_ae, scp_ae)
    if not assoc.is_established:
        return []
    try:
        # C-FIND tanpa key sama sekali invalid (identifier None) — wildcard
        # valid = key dengan nilai kosong. Jamin minimal satu key.
        if not any(str(getattr(model, a, "") or "") for a in
                   ("AccessionNumber", "PatientID", "ScheduledStationAETitle")):
            model = Dataset()
            model.AccessionNumber = ""
        results: list[Dataset] = []
        for status, ds in assoc.send_c_find(model, query_model=MWL_FIND):
            if status and status.Status == 0xFF00 and ds:
                results.append(ds)
        return results
    finally:
        assoc.release()


def _associate_mwl(host, port, scu_ae, scp_ae):
    ae = AE(ae_title=scu_ae)
    ae.add_requested_context(MWL_FIND)
    assoc = ae.associate(host, port, ae_title=scp_ae)
    if assoc.is_established:
        # SCP fetch worklist dari RIS bisa lambat — perpanjang batas tunggu
        assoc.dimse_timeout = 30
    return assoc


def mpps_send(ds: Dataset, host: str = "localhost", port: int = 4244,
              scu_ae: str = "OMC_CONSOLE", scp_ae: str = "MPPS_SCP") -> bool:
    """MPPS N-CREATE (jika status) / N-SET ke Integration :4244.

    `ds` berisi MPPS dataset lengkap (PerformedProcedureStepSequence, dst).
    Return True bila status sukses.
    """
    assoc = _associate_mpps(host, port, scu_ae, scp_ae)
    if not assoc.is_established:
        return False
    try:
        # signature: send_n_create(dataset, class_uid, instance_uid)
        # return: (status_dataset, response_dataset)
        if ds.get("PerformedProcedureStepStatus") == "IN PROGRESS":
            status, _ = assoc.send_n_create(ds, str(MPPS), str(ds.PerformedProcedureStepID))
        else:
            status, _ = assoc.send_n_set(ds, str(MPPS), str(ds.PerformedProcedureStepID))
        return bool(status) and status.Status == 0x0000
    finally:
        assoc.release()


def _associate_mpps(host, port, scu_ae, scp_ae):
    ae = AE(ae_title=scu_ae)
    ae.add_requested_context(MPPS)
    return ae.associate(host, port, ae_title=scp_ae)
