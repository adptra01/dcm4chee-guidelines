"""Integration Service — ORP.

MS0: bootable (GET /health). Adapter MORBIS/FHIR/HL7 dibangun bertahap (MS5+).
"""
from fastapi import FastAPI

app = FastAPI(
    title="ORP Integration Service",
    version="0.1.0",
    description="Adapter SIMRS eksternal (MORBIS/FHIR/HL7) — penerjemah kontrak",
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "integration-service", "version": "0.1.0"}


@app.get("/")
def root() -> dict:
    return {"service": "integration-service", "docs": "/docs", "health": "/health"}
