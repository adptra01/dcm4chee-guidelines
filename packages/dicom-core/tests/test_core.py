"""MS1: parser + preview (tanpa server DICOM)."""
import socket
from pathlib import Path

import pytest

from dicom_core import echo, parse, preview

SAMPLE = Path(__file__).parent.parent.parent.parent / "sample-data" / "dicom" / \
    "DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"

pytestmark = pytest.mark.skipif(not SAMPLE.exists(), reason="sample DICOM belum ada")

# Orthanc lokal (docker compose) — test di-skip bila mati, bukan gagal
ORTHANC_UP = socket.socket().connect_ex(("localhost", 4242)) == 0


def test_parse_metadata():
    meta = parse(str(SAMPLE))
    assert meta["Rows"] > 0 and meta["Columns"] > 0
    assert meta["Modality"]
    assert meta["SOPInstanceUID"]


def test_preview_returns_png_bytes():
    png = preview(str(SAMPLE))
    assert png[:8] == b"\x89PNG\r\n\x1a\n"  # magic PNG


def test_preview_custom_window():
    png = preview(str(SAMPLE), wc=128, ww=256)
    assert png[:8] == b"\x89PNG\r\n\x1a\n"


@pytest.mark.skipif(not ORTHANC_UP, reason="Orthanc local mati")
def test_echo_orthanc():
    assert echo("localhost", 4242)
