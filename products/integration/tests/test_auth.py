"""API key auth — aktif hanya bila API_KEYS diset (nonaktif default)."""
import importlib
import os

import pytest
from fastapi.testclient import TestClient

import app.main as main


@pytest.fixture(autouse=True)
def _clean_env_after():
    """Pulihkan module ke state default (API_KEYS kosong) setelah tiap test —
    hindari bocor ke file test lain (urutan alphabetical)."""
    yield
    os.environ.pop("API_KEYS", None)
    importlib.reload(main)


def test_no_key_required_when_unset(monkeypatch):
    """Tanpa API_KEYS di env → endpoint terbuka (dev)."""
    monkeypatch.delenv("API_KEYS", raising=False)
    importlib.reload(main)
    client = TestClient(main.app)
    r = client.post("/morbis/sep", json={"no_kartu": "0001"})
    assert r.status_code == 200


def test_key_required_when_set(monkeypatch):
    """API_KEYS diset → tanpa header 401, dengan key 200."""
    monkeypatch.setenv("API_KEYS", "dev-key-123")
    importlib.reload(main)
    client = TestClient(main.app)

    assert client.post("/morbis/sep", json={"no_kartu": "0001"}).status_code == 401
    assert client.post("/hl7/message", json={"message": "x"}).status_code == 401

    r = client.post("/morbis/sep", json={"no_kartu": "0001"},
                    headers={"X-API-Key": "dev-key-123"})
    assert r.status_code == 200

    r2 = client.post("/hl7/message", json={"message": "MSH|^~\\&|SIMRS|R|RIS|R|20260805120000||ADT^A01|M|P|2.3\rPID|1||X||N|||\r"},
                     headers={"X-API-Key": "dev-key-123"})
    assert r2.status_code == 200


def test_wrong_key_rejected(monkeypatch):
    monkeypatch.setenv("API_KEYS", "k1")
    importlib.reload(main)
    client = TestClient(main.app)
    assert client.post("/morbis/sep", json={"no_kartu": "1"},
                       headers={"X-API-Key": "salah"}).status_code == 401
