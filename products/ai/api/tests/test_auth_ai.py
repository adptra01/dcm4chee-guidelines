"""AI API key auth (ADR-006) — aktif hanya bila AI_API_KEYS diset."""
import importlib
import os

import pytest
from fastapi.testclient import TestClient

import app.main as main


@pytest.fixture(autouse=True)
def _clean_env_after():
    yield
    os.environ.pop("AI_API_KEYS", None)
    importlib.reload(main)


def test_missing_instance_open_when_unset(monkeypatch):
    monkeypatch.delenv("AI_API_KEYS", raising=False)
    importlib.reload(main)
    client = TestClient(main.app)
    # tanpa key & tanpa AI_API_KEYS → auth dilewati, 404 (instance tak ada) bukan 401
    assert client.post("/analyze/instance/tidak-ada").status_code == 404


def test_inference_requires_key_when_set(monkeypatch):
    monkeypatch.setenv("AI_API_KEYS", "dev-key")
    importlib.reload(main)
    client = TestClient(main.app)

    assert client.post("/analyze/instance/tidak-ada").status_code == 401
    r = client.post("/analyze/instance/tidak-ada", headers={"X-API-Key": "dev-key"})
    assert r.status_code == 404  # auth lolos, instance tak ada


def test_wrong_key_rejected(monkeypatch):
    monkeypatch.setenv("AI_API_KEYS", "dev-key")
    importlib.reload(main)
    client = TestClient(main.app)
    assert client.post("/analyze/instance/tidak-ada",
                       headers={"X-API-Key": "salah"}).status_code == 401
