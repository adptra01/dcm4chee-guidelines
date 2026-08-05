"""AI Service — ORP.

Membaca instance DICOM dari Orthanc, analisis v1 (statistik), memberi saran.
Saran, bukan modifikasi gambar. Model ML di MS8.
"""
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from app import analyze

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


@app.post("/analyze/instance/{orthanc_id}")
def analyze_instance(orthanc_id: str) -> dict:
    """Analisis satu instance DICOM dari Orthanc (statistik v1)."""
    try:
        return analyze.analyze_instance(orthanc_id)
    except ValueError as e:
        raise HTTPException(404, str(e)) from e


@app.get("/analyze/series/{series_id}")
def analyze_series(series_id: str) -> dict:
    """Analisis semua instance dalam satu series Orthanc."""
    return analyze.analyze_series(series_id)
