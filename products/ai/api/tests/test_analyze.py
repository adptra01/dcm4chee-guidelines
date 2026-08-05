"""AI: analisis statistik instance dari Orthanc (skip bila Orthanc mati)."""
import socket
import json
import urllib.request

import pytest
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)

ORTHANC_UP = socket.socket().connect_ex(("localhost", 8042)) == 0


def test_health():
    assert client.get("/health").json()["status"] == "ok"


def test_analyze_missing_instance():
    r = client.post("/analyze/instance/tidak-ada")
    assert r.status_code == 404  # HTTPError Orthanc → ValueError → 404


@pytest.mark.skipif(not ORTHANC_UP, reason="Orthanc mati")
def test_analyze_real_instance():
    ids = json.loads(urllib.request.urlopen("http://localhost:8042/instances").read())
    assert ids, "Orthanc tanpa instance"
    r = client.post(f"/analyze/instance/{ids[0]}")
    assert r.status_code == 200
    body = r.json()
    assert body["rows"] > 0 and body["columns"] > 0
    assert body["engine"] == "statistik-v1"
    assert "finding" in body


@pytest.mark.skipif(not ORTHANC_UP, reason="Orthanc mati")
def test_analyze_series():
    series = json.loads(urllib.request.urlopen("http://localhost:8042/series").read())
    if not series:
        pytest.skip("tanpa series")
    r = client.get(f"/analyze/series/{series[0]}")
    assert r.status_code == 200
    assert r.json()["count"] >= 1
