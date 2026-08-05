"""Integrasi MORBIS (mock mode) — SEP + klaim."""
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_sep_mock():
    r = client.post("/morbis/sep", json={"no_kartu": "0001234567890"})
    assert r.status_code == 200
    body = r.json()
    assert body["mode"] == "mock"
    assert body["sep"].startswith("0101")
    assert body["status"] == "aktif"


def test_claim_mock():
    r = client.post("/morbis/claim", json={
        "no_sep": "0101001234567890001V0001",
        "no_kartu": "0001234567890",
        "biaya": 250000,
    })
    assert r.status_code == 200
    body = r.json()
    assert body["mode"] == "mock"
    assert len(body["trackingId"]) == 16


def test_mode_endpoint():
    r = client.get("/morbis/mode")
    assert r.status_code == 200
    assert r.json()["mode"] in ("mock", "real")
