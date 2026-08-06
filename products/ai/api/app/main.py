"""AI Service — ORP.

Membaca instance DICOM dari Orthanc, analisis v1 (statistik), memberi saran.
Saran, bukan modifikasi gambar. Model ML di MS8.
"""
import os

from fastapi import Depends, FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import APIKeyHeader

from app import analyze

# API key dari env (koma-terpisah). Kosong → auth nonaktif (dev). ADR-006 bagian 3.
API_KEYS = {k.strip() for k in os.getenv("AI_API_KEYS", "").split(",") if k.strip()}
API_KEY_HEADER = APIKeyHeader(name="X-API-Key", auto_error=False)


def require_key(x_api_key: str | None = Depends(API_KEY_HEADER)):
    """Autorisasi API key bila AI_API_KEYS dikonfigurasi."""
    if API_KEYS and x_api_key not in API_KEYS:
        raise HTTPException(401, "API key tidak valid")


app = FastAPI(
    title="ORP AI Service",
    version="0.2.0",
    description="Inference & CAD untuk citra radiologi (saran, bukan modifikasi gambar)",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "ai-service", "version": "0.2.0"}


@app.post("/analyze/instance/{orthanc_id}", dependencies=[Depends(require_key)])
def analyze_instance(orthanc_id: str) -> dict:
    """Analisis satu instance DICOM dari Orthanc (statistik v1)."""
    try:
        return analyze.analyze_instance(orthanc_id)
    except ValueError as e:
        raise HTTPException(404, str(e)) from e


@app.get("/analyze/series/{series_id}", dependencies=[Depends(require_key)])
def analyze_series(series_id: str) -> dict:
    """Analisis semua instance dalam satu series Orthanc."""
    try:
        return analyze.analyze_series(series_id)
    except ValueError as e:
        raise HTTPException(404, str(e)) from e
