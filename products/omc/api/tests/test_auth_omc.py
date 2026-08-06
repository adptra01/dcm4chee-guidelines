"""OMC API key auth (ADR-006) — aktif hanya bila OMC_API_KEYS diset."""
import importlib
import os

import pytest
from fastapi.testclient import TestClient

import app.main as main


@pytest.fixture(autouse=True)
def _clean_env_after():
    yield
    os.environ.pop("OMC_API_KEYS", None)
    importlib.reload(main)


def test_mutation_open_when_unset(monkeypatch):
    monkeypatch.delenv("OMC_API_KEYS", raising=False)
    importlib.reload(main)
    client = TestClient(main.app)
    r = client.post("/studies/tidak-ada/store")
    # tanpa key, tanpa API_KEYS → dilewati auth, 404 (study tak ada) bukan 401
    assert r.status_code == 404


def test_store_requires_key_when_set(monkeypatch):
    monkeypatch.setenv("OMC_API_KEYS", "dev-key")
    importlib.reload(main)
    client = TestClient(main.app)

    assert client.post("/studies/tidak-ada/store").status_code == 401
    r = client.post("/studies/tidak-ada/store", headers={"X-API-Key": "dev-key"})
    assert r.status_code == 404  # auth lolos, study tak ada


def test_wrong_key_rejected(monkeypatch):
    monkeypatch.setenv("OMC_API_KEYS", "dev-key")
    importlib.reload(main)
    client = TestClient(main.app)
    assert client.post("/studies/tidak-ada/store",
                       headers={"X-API-Key": "salah"}).status_code == 401
