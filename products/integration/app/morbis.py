"""MORBIS (BPJS VClaim) adapter — SEP + klaim.

Mode: mock (default, tanpa kredensial) atau real (sandbox/prod via .env).
Kredensial TIDAK pernah di-hardcode — dibaca dari env:
  MORBIS_BASE_URL, MORBIS_CONS_ID, MORBIS_SECRET, MORBIS_USER_KEY, MORBIS_MODE
"""
from __future__ import annotations

import hashlib
import hmac
import os
from datetime import datetime, timedelta

import httpx

BASE = os.getenv("MORBIS_BASE_URL", "https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev")
CONS_ID = os.getenv("MORBIS_CONS_ID", "")
SECRET = os.getenv("MORBIS_SECRET", "")
USER_KEY = os.getenv("MORBIS_USER_KEY", "")
MODE = os.getenv("MORBIS_MODE", "mock").lower()


def _signature() -> tuple[str, str, str]:
    """Header VClaim: X-timestamp, X-signature (HMAC SHA256), X-cons-id."""
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    digest = hmac.new(SECRET.encode(), f"{CONS_ID}&{ts}".encode(), hashlib.sha256).hexdigest()
    return ts, digest, CONS_ID


def _headers() -> dict:
    ts, sig, cons = _signature()
    return {
        "X-cons-id": cons,
        "X-timestamp": ts,
        "X-signature": sig,
        "X-username": USER_KEY,
        "Content-Type": "application/json",
    }


def sep(nomor_kartu: str, tanggal: str | None = None) -> dict:
    """Buat SEP (Surat Eligibilitas Peserta). Mock: kembalikan contoh SEP valid."""
    if MODE == "mock":
        return {
            "mode": "mock",
            "noKartu": nomor_kartu,
            "sep": f"0101{nomor_kartu[-8:]}001V0001",
            "nama": "Peserta BPJS Contoh",
            "tglSep": tanggal or datetime.now().strftime("%Y-%m-%d"),
            "diagnosa": "I10",
            "poliklinik": "RAD",
            "status": "aktif",
        }
    r = httpx.post(f"{BASE}/SEP/2.0/insert",
                   headers=_headers(), json={"request": {
                       "t_sep": {"noKartu": nomor_kartu, "tglSep": tanggal or "", "jnsPelayanan": "2"}}} , timeout=15)
    r.raise_for_status()
    return r.json()


def claim(no_sep: str, no_kartu: str, biaya: int) -> dict:
    """Kirim klaim rawat jalan ringkas. Mock: return tracking_id."""
    if MODE == "mock":
        return {
            "mode": "mock",
            "noSep": no_sep,
            "noKartu": no_kartu,
            "trackingId": hashlib.md5(f"{no_sep}{biaya}".encode()).hexdigest()[:16],
            "biaya": biaya,
            "status": "diterima (mock)",
        }
    r = httpx.post(f"{BASE}/sep/klaim", headers=_headers(),
                   json={"request": {"noSep": no_sep, "noKartu": no_kartu, "biaya": biaya}}, timeout=15)
    r.raise_for_status()
    return r.json()
