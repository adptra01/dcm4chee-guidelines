"""OMC API — Open Modality Console backend.

MS2 vertical slice: import DICOM → queue → preview → C-STORE ke Orthanc.
Antrean in-memory (per-proses); storage file di data/incoming/.
"""
import shutil
from pathlib import Path
from uuid import uuid4

from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response

from dicom_core import parse, preview, store

STORAGE = Path(__file__).parent.parent / "data" / "incoming"

app = FastAPI(
    title="OMC API",
    version="0.2.0",
    description="Open Modality Console — import, preview, queue, MWL, MPPS, DICOM",
)

# queue: id -> {"metadata": dict, "path": str, "stored": bool}
QUEUE: dict[str, dict] = {}

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


@app.post("/studies/import")
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
    QUEUE[study_id] = {"metadata": meta, "path": str(path), "stored": False}
    return {"study_id": study_id, "metadata": meta}


@app.get("/studies")
def list_studies() -> dict:
    """Isi antrean."""
    return {"count": len(QUEUE), "studies": [
        {"study_id": sid, **rec["metadata"], "stored": rec["stored"]}
        for sid, rec in QUEUE.items()
    ]}


@app.get("/studies/{study_id}/preview")
def study_preview(study_id: str) -> Response:
    """Preview PNG (window/level dari tag file)."""
    rec = QUEUE.get(study_id)
    if not rec:
        raise HTTPException(404, "study tidak ada")
    png = preview(rec["path"])
    return Response(content=png, media_type="image/png")


@app.post("/studies/{study_id}/store")
def study_store(study_id: str) -> dict:
    """C-STORE ke Orthanc (DICOM 4242, AE ORTHANC)."""
    rec = QUEUE.get(study_id)
    if not rec:
        raise HTTPException(404, "study tidak ada")
    status = store(rec["path"])
    if status is None:
        raise HTTPException(502, "gagal terhubung ke Orthanc")
    rec["stored"] = status == 0x0000
    return {"study_id": study_id, "status": hex(status), "stored": rec["stored"]}
