"""MS2: vertical slice — import → queue → preview → store (Orthanc optional)."""
import json
import socket
import urllib.request
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app import queue_store
from app.main import app

SAMPLE = Path(__file__).parent.parent.parent.parent.parent / "sample-data" / "dicom" / \
    "DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"

ORTHANC_UP = socket.socket().connect_ex(("localhost", 4242)) == 0

client = TestClient(app)


def _orthanc_instance_ids() -> set[str]:
    return set(json.loads(urllib.request.urlopen("http://localhost:8042/instances").read()))


def _orthanc_delete(ids: set[str]):
    for iid in ids:
        urllib.request.urlopen(urllib.request.Request(
            f"http://localhost:8042/instances/{iid}", method="DELETE"))


def setup_function():
    """Bersihkan antrean SQLite + file incoming antar test."""
    queue_store._run("DELETE FROM studies")
    for f in Path(queue_store.DB).parent.glob("incoming/*.dcm"):
        f.unlink(missing_ok=True)


def test_import_and_list():
    with SAMPLE.open("rb") as f:
        r = client.post("/studies/import", files={"file": ("dx.dcm", f, "application/dicom")})
    assert r.status_code == 200
    sid = r.json()["study_id"]
    assert r.json()["metadata"]["Rows"] > 0

    studies = client.get("/studies").json()
    assert studies["count"] == 1
    assert studies["studies"][0]["study_id"] == sid


def test_import_invalid():
    r = client.post("/studies/import", files={"file": ("x.bin", b"not dicom", "application/octet-stream")})
    assert r.status_code == 400


def test_preview_png():
    with SAMPLE.open("rb") as f:
        sid = client.post("/studies/import", files={"file": ("dx.dcm", f, "application/dicom")}).json()["study_id"]
    r = client.get(f"/studies/{sid}/preview")
    assert r.status_code == 200
    assert r.content[:8] == b"\x89PNG\r\n\x1a\n"


def test_preview_404():
    assert client.get("/studies/nonexistent/preview").status_code == 404


def test_store_to_orthanc():
    if not ORTHANC_UP:
        pytest.skip("Orthanc mati")
    with SAMPLE.open("rb") as f:
        sid = client.post("/studies/import", files={"file": ("dx.dcm", f, "application/dicom")}).json()["study_id"]
    before = _orthanc_instance_ids()
    r = client.post(f"/studies/{sid}/store")
    assert r.status_code == 200
    assert r.json()["stored"] is True
    # teardown: hapus HANYA instance baru dari test ini (studi asli tetap utuh)
    _orthanc_delete(_orthanc_instance_ids() - before)
