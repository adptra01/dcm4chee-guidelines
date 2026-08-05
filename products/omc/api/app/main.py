"""OMC API — Open Modality Console backend.

MS0: bootable (GET /health). Kontrak API per produk dibangun bertahap (MS2).
"""
from fastapi import FastAPI

app = FastAPI(
    title="OMC API",
    version="0.1.0",
    description="Open Modality Console — import, preview, queue, MWL, MPPS, DICOM",
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "omc-api", "version": "0.1.0"}


@app.get("/")
def root() -> dict:
    return {"service": "omc-api", "docs": "/docs", "health": "/health"}
