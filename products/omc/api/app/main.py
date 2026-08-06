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

from dicom_core import echo, parse, preview, store

from app import queue_store

STORAGE = Path(__file__).parent.parent / "data" / "incoming"

# Target Orthanc — override via env (Settings di UI OMC)
ORTHANC_HOST = os.getenv("OMC_ORTHANC_HOST", "localhost")
ORTHANC_PORT = int(os.getenv("OMC_ORTHANC_PORT", "4242"))
SCU_AE = os.getenv("OMC_SCU_AE", "OMC_CONSOLE")
SCP_AE = os.getenv("OMC_SCP_AE", "ORTHANC")

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
    return {"study_id": study_id, "status": hex(status), "stored": status == 0x0000}


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
