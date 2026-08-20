"""OMC API — Open Modality Console backend.

MS2 vertical slice: import DICOM → queue → preview → C-STORE ke Orthanc.
Antrean in-memory (per-proses); storage file di data/incoming/.
"""
import os
import shutil
from pathlib import Path
from uuid import uuid4

from fastapi import Depends, FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response
from fastapi.security import APIKeyHeader

from dicom_core import echo, mpps_send, mwl_query, parse, preview, store

from app import queue_store

STORAGE = Path(__file__).parent.parent / "data" / "incoming"

# Target Orthanc — override via env (Settings di UI OMC)
ORTHANC_HOST = os.getenv("OMC_ORTHANC_HOST", "localhost")
ORTHANC_PORT = int(os.getenv("OMC_ORTHANC_PORT", "4242"))
SCU_AE = os.getenv("OMC_SCU_AE", "OMC_CONSOLE")
SCP_AE = os.getenv("OMC_SCP_AE", "ORTHANC")

# Target Integration (MWL SCP :4243, MPPS SCP :4244) — v0.5
INT_HOST = os.getenv("OMC_INT_HOST", "localhost")
MWL_PORT = int(os.getenv("OMC_MWL_PORT", "4243"))
MPPS_PORT = int(os.getenv("OMC_MPPS_PORT", "4244"))
MWL_SCP_AE = os.getenv("OMC_MWL_SCP_AE", "MWL_SCP")
MPPS_SCP_AE = os.getenv("OMC_MPPS_SCP_AE", "MPPS_SCP")

# API key dari env (koma-terpisah). Kosong → auth nonaktif (dev). ADR-006 bagian 3.
API_KEYS = {k.strip() for k in os.getenv("OMC_API_KEYS", "").split(",") if k.strip()}
API_KEY_HEADER = APIKeyHeader(name="X-API-Key", auto_error=False)


def require_key(x_api_key: str | None = Depends(API_KEY_HEADER)):
    """Autorisasi API key bila OMC_API_KEYS dikonfigurasi."""
    if API_KEYS and x_api_key not in API_KEYS:
        raise HTTPException(401, "API key tidak valid")


app = FastAPI(
    title="OMC API",
    version="0.2.0",
    description="Open Modality Console — import, preview, queue, MWL, MPPS, DICOM",
)

# Antrean persisten: SQLite di data/queue.db (survive restart)

# CORS: izinkan origin dev SvelteKit (console)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:5173", "http://127.0.0.1:5173"],
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "omc-api", "version": "0.2.0"}


@app.post("/studies/import", dependencies=[Depends(require_key)])
async def import_study(file: UploadFile = File(...)) -> dict:
    """Terima file DICOM, simpan ke incoming/, masukkan antrean."""
    STORAGE.mkdir(parents=True, exist_ok=True)
    study_id = uuid4().hex[:12]
    path = STORAGE / f"{study_id}.dcm"
    with path.open("wb") as f:
        shutil.copyfileobj(file.file, f)
    try:
        meta = parse(str(path))
    except Exception as e:  # bukan DICOM valid
        path.unlink(missing_ok=True)
        raise HTTPException(400, f"bukan file DICOM valid: {e}")
    queue_store.insert(study_id, meta, str(path))
    return {"study_id": study_id, "metadata": meta}


@app.get("/studies")
def list_studies() -> dict:
    """Isi antrean (persisten)."""
    recs = queue_store.list_all()
    return {"count": len(recs), "studies": [
        {"study_id": r["study_id"], **r["metadata"], "stored": r["stored"]}
        for r in recs
    ]}


@app.get("/studies/{study_id}/preview")
def study_preview(study_id: str) -> Response:
    """Preview PNG (window/level dari tag file)."""
    rec = queue_store.get(study_id)
    if not rec:
        raise HTTPException(404, "study tidak ada")
    png = preview(rec["path"])
    return Response(content=png, media_type="image/png")


@app.post("/studies/{study_id}/store", dependencies=[Depends(require_key)])
def study_store(study_id: str) -> dict:
    """C-STORE ke Orthanc (DICOM 4242, AE ORTHANC)."""
    rec = queue_store.get(study_id)
    if not rec:
        raise HTTPException(404, "study tidak ada")
    status = store(rec["path"], host=ORTHANC_HOST, port=ORTHANC_PORT,
                   scu_ae=SCU_AE, scp_ae=SCP_AE)
    if status is None:
        raise HTTPException(502, "gagal terhubung ke Orthanc")
    if status == 0x0000:
        queue_store.mark_stored(study_id)
        mpps_completed(rec)  # beri tahu RIS via Integration (MPPS N-SET)
    return {"study_id": study_id, "status": hex(status), "stored": status == 0x0000}


def mpps_completed(rec: dict) -> bool:
    """Build dataset MPPS COMPLETED dari metadata studi, kirim ke :4244.

    Gagal MPPS tidak menggagalkan store (DICOM sudah di Orthanc) — cukup
    dicatat. `ponytail: MPPS best-effort, retry bila alur transaksi penuh`.
    """
    from datetime import datetime
    from pydicom.dataset import Dataset

    ds = Dataset()
    meta = rec.get("metadata", {})
    ds.PatientName = meta.get("PatientName", "")
    ds.PatientID = meta.get("PatientID", "")
    ds.AccessionNumber = meta.get("AccessionNumber", "")
    ds.PerformedProcedureStepID = str(rec["study_id"])
    ds.PerformedProcedureStepStatus = "COMPLETED"
    ds.PerformedStationAETitle = SCU_AE
    now = datetime.now()
    ds.PerformedProcedureStepStartDate = now.strftime("%Y%m%d")
    ds.PerformedProcedureStepStartTime = now.strftime("%H%M%S")
    ds.PerformedProcedureStepEndDate = now.strftime("%Y%m%d")
    ds.PerformedProcedureStepEndTime = now.strftime("%H%M%S")
    return mpps_send(ds, host=INT_HOST, port=MPPS_PORT,
                     scu_ae=SCU_AE, scp_ae=MPPS_SCP_AE)


@app.get("/settings")
def settings() -> dict:
    """Konfigurasi target DICOM (dari env) + status koneksi via C-ECHO."""
    return {
        "host": ORTHANC_HOST,
        "port": ORTHANC_PORT,
        "scu_ae": SCU_AE,
        "scp_ae": SCP_AE,
        "echoc": echo(ORTHANC_HOST, ORTHANC_PORT, SCU_AE, SCP_AE),
    }


@app.get("/worklist")
def worklist() -> dict:
    """MWL C-FIND ke Integration :4243 — daftar jadwal modalitas."""
    from pydicom.dataset import Dataset
    items = mwl_query(Dataset(), host=INT_HOST, port=MWL_PORT,
                      scu_ae=SCU_AE, scp_ae=MWL_SCP_AE)
    return {"count": len(items), "worklist": [
        {
            "patient": getattr(d, "PatientName", ""),
            "patient_id": getattr(d, "PatientID", ""),
            "accession": getattr(d, "AccessionNumber", ""),
            "modality": (d.ScheduledProcedureStepSequence[0].get("Modality", "")
                         if getattr(d, "ScheduledProcedureStepSequence", None) else ""),
            "start_date": (d.ScheduledProcedureStepSequence[0].get("ScheduledProcedureStepStartDate", "")
                           if getattr(d, "ScheduledProcedureStepSequence", None) else ""),
        }
        for d in items
    ]}

# FHIR R4 endpoints — sesuai regulasi SATUSEHAT (phase 1 & 2)
@app.get("/fhir/Patient", dependencies=[Depends(require_key)])
def fhir_patient() -> dict:
    """Fhir Patient endpoint — datasample untuk SATUSEHAT compliance."""
    return {
        "id": "1",
        "name": "Pasien Contoh",
        "telecom": [{"system": "phone", "value": "081234567890"}],
        "gender": "male",
        "birthDate": "1990-01-01",
        "address": "Jakarta",
        "meta": {"tag": [{"system": "status", "code": "internal"}]},
    }


@app.get("/fhir/Observation", dependencies=[Depends(require_key)])
def fhir_observation() -> dict:
    """Fhir Observation endpoint."""
    return {
        "id": "2",
        "status": "final",
        "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode", "code": "laboratory"}]}],
        "code": {"coding": [{"system": "http://loinc.org", "code": "718-7"}]},
        "valueBase64Binary": "",
    }


@app.get("/fhir/DiagnosticReport", dependencies=[Depends(require_key)])
def fhir_diagnostic_report() -> dict:
    """Fhir DiagnosticReport endpoint."""
    return {
        "id": "3",
        "status": "final",
        "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode", "code": "report"}]}],
        "code": {"coding": [{"system": "http://loinc.org", "code": "11317-3"}]},
        "subject": {"reference": "Patient/1"},
        "effectiveDateTime": "2026-08-20",
    }


@app.get("/fhir/Procedure", dependencies=[Depends(require_key)])
def fhir_procedure() -> dict:
    """Fhir Procedure endpoint."""
    return {
        "id": "4",
        "status": "completed",
        "category": [{"coding": [{"system": "http://snomed.info/sct", "code": "44054006"}]}],
        "code": {"coding": [{"system": "http://snomed.info/sct", "code": "71061007"}]},
        "performedDateTime": "2026-08-20T10:00:00",
    }


@app.get("/fhir/MedicationRequest", dependencies=[Depends(require_key)])
def fhir_medication_request() -> dict:
    """Fhir MedicationRequest endpoint."""
    return {
        "id": "5",
        "status": "active",
        "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode", "code": " medication"}]}],
        "code": {"coding": [{"system": "http://hl7.org/fhir/CodeSystem/rxnorm", "code": "383091"}]},
        "subject": {"reference": "Patient/1"},
        "requester": {"reference": "Practitioner/1"},
    }


@app.get("/fhir/ServiceRequest", dependencies=[Depends(require_key)])
def fhir_service_request() -> dict:
    """Fhir ServiceRequest endpoint."""
    return {
        "id": "6",
        "status": "active",
        "category": [{"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v3-ActCode", "code": "service request"}]}],
        "code": {"coding": [{"system": "http://loinc.org", "code": "44176-9"}]},
        "subject": {"reference": "Patient/1"},
        "encounter": {"reference": "Encounter/1"},
    }
