"""AI Service — ORP.

MS0: bootable (GET /health). Model & inferensi dibangun bertahap (MS5+).
"""
from fastapi import FastAPI

app = FastAPI(
    title="ORP AI Service",
    version="0.1.0",
    description="Inference & CAD untuk citra radiologi (saran, bukan modifikasi gambar)",
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "ai-service", "version": "0.1.0"}


@app.get("/")
def root() -> dict:
    return {"service": "ai-service", "docs": "/docs", "health": "/health"}
