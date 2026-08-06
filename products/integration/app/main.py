"""Integration Service — ORP.

Adapter SIMRS eksternal (MORBIS/BPJS, HL7, MWL) — penerjemah kontrak,
bukan penyimpan data. Service menghadap eksternal → proteksi API key.
"""
import os

from fastapi import Depends, FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import PlainTextResponse
from fastapi.security import APIKeyHeader
from pydantic import BaseModel

from app import hl7, morbis, mwl

# API key dari env (koma-terpisah). Kosong → auth nonaktif (dev).
API_KEYS = {k.strip() for k in os.getenv("API_KEYS", "").split(",") if k.strip()}
API_KEY_HEADER = APIKeyHeader(name="X-API-Key", auto_error=False)


def require_key(x_api_key: str | None = Depends(API_KEY_HEADER)):
    """Autorisasi API key bila API_KEYS dikonfigurasi."""
    if API_KEYS and x_api_key not in API_KEYS:
        raise HTTPException(401, "API key tidak valid")


app = FastAPI(
    title="ORP Integration Service",
    version="0.3.0",
    description="Adapter SIMRS eksternal (MORBIS/FHIR/HL7) — penerjemah kontrak",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "integration-service", "version": "0.2.0"}


class SepRequest(BaseModel):
    no_kartu: str
    tanggal: str | None = None


class ClaimRequest(BaseModel):
    no_sep: str
    no_kartu: str
    biaya: int


@app.post("/morbis/sep", dependencies=[Depends(require_key)])
def create_sep(req: SepRequest) -> dict:
    """Buat SEP BPJS (mock default; real bila MORBIS_MODE=real)."""
    return morbis.sep(req.no_kartu, req.tanggal)


@app.post("/morbis/claim", dependencies=[Depends(require_key)])
def submit_claim(req: ClaimRequest) -> dict:
    """Kirim klaim ke BPJS (mock default; real bila MORBIS_MODE=real)."""
    return morbis.claim(req.no_sep, req.no_kartu, req.biaya)


@app.get("/morbis/mode")
def morbis_mode() -> dict:
    return {"mode": morbis.MODE, "base_url": morbis.BASE}


class Hl7Message(BaseModel):
    message: str


@app.post("/hl7/message", dependencies=[Depends(require_key)], response_class=PlainTextResponse)
def hl7_inbound(req: Hl7Message) -> str:
    """Terima HL7 v2 (ADT-A01) → buat pasien di RIS → ACK."""
    msh = hl7.parse(req.message).get("MSH", [])
    msg_type = msh[0][8] if msh and len(msh[0]) > 8 else ""
    if msg_type == "ADT^A01":
        return hl7.adt_ack(req.message)
    raise HTTPException(400, f"tipe pesan tak didukung: {msg_type}")
