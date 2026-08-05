"""HL7 v2 — parser minimal + penerjemah kontrak (ADT-A01 → RIS patient).

SIMRS eksternal kirim ADT-A01; integration menerjemahkan → POST /api/patients
di RIS. ORM-O01 di-generate dari order RIS. Pipe-delimited, tanpa library.
"""
from __future__ import annotations

import os

import httpx

RIS_URL = os.getenv("RIS_URL", "https://ris.ddev.site/api")
RIS_VERIFY = os.getenv("RIS_VERIFY", "false").lower() == "true"


def parse(message: str) -> dict:
    """Parsing segment utama: MSH, PID, ORC, OBR. Hasil: dict per segment."""
    out: dict[str, list[str]] = {}
    for seg in message.strip().splitlines():
        seg = seg.rstrip("\r")
        if not seg:
            continue
        name = seg[:3]
        out.setdefault(name, []).append(seg.split("|"))
    return out


def extract_patient(parsed: dict) -> dict | None:
    """Dari PID segment: patient_id (MRN), name, sex, birthdate."""
    for fields in parsed.get("PID", []):
        if len(fields) < 6:
            continue
        return {
            "patient_id": fields[3] if len(fields) > 3 else "",
            "name": fields[5] if len(fields) > 5 else "",
            "sex": fields[8][:1] if len(fields) > 8 else "",
            "birthdate": fields[7] if len(fields) > 7 else None,
        }
    return None


def adt_ack(message: str) -> str:
    """Proses ADT-A01 → buat pasien di RIS → ACK HL7 (MSA)."""
    parsed = parse(message)
    patient = extract_patient(parsed)
    if not patient or not patient["patient_id"]:
        return _ack(parsed, "AR", "PID tidak valid / tanpa MRN")

    r = httpx.post(f"{RIS_URL}/patients", json=patient, verify=RIS_VERIFY, timeout=15)
    if r.status_code in (200, 201):
        return _ack(parsed, "AA", "pasien dibuat di RIS")
    return _ack(parsed, "AR", f"RIS menolak: {r.status_code}")


def _ack(parsed: dict, code: str, note: str) -> str:
    msh = parsed.get("MSH", [["MSH", "^~\\&", "", "", "", "", "", "", ""]])[0]
    def field(i: int) -> str:
        return msh[i] if i < len(msh) else ""
    # MSH-9 control: ACK^A01
    ts = "20260805120000"  # ponytail: waktu statis di generator sederhana
    return (f"MSH|^~\\&|{field(3)}|{field(4)}|{field(5)}|{field(6)}|{ts}||ACK^A01|"
            f"{field(10)}|P|2.3\r"
            f"MSA|{code}|{field(10)}|{note}\r")


def orm_order(order: dict) -> str:
    """Generate ORM-O01 dari order RIS (order + patient)."""
    o = order["order"]
    p = o["patient"]
    name = p["name"].replace(" ", "^") if p.get("name") else ""
    birthdate = (p.get("birthdate") or "").replace("-", "")
    return (
        "MSH|^~\\&|RIS|RSUD|SIMRS|RSUD|20260805120000||ORM^O01|1|P|2.3\r"
        f"PID|1||{p['patient_id']}||{name}||{birthdate}|{p.get('sex', '')}\r"
        f"ORC|NW|{o['order_no']}|||{o['status']}\r"
        f"OBR|1|{o['order_no']}||{o['modality']}^RADIOLOGI|||{o.get('requested_at')}\r"
    )
