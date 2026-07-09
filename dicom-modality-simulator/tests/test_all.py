import os, sys, tempfile, io
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from PIL import Image
from pydicom.dataset import Dataset as DCM, FileMetaDataset

import config
from models.patient import WorklistItem
from dicom.echo import echo
from dicom.store import store
from dicom.dataset import dump_info, set_patient_info
from dicom.image import jpg_to_dicom
from dicom.worklist import build_query, parse_response
from dicom.worklist_mwl import build_mwl_query, parse_mwl_response, query_mwl_local
from dicom.association import create_ae
from dicom.retrieve import _status_code
from dicom.mpps import _status_code as mpps_status
from dicom.stgcmt import stgcmt_make_handler


def test_config():
    cfg = config.load()
    assert cfg["ae_title"] == "SIMULATOR"
    config.save({"ae_title": "TEST"})
    assert config.load()["ae_title"] == "TEST"
    config.save(cfg)
    assert config.load()["ae_title"] == "SIMULATOR"


def test_worklist_item():
    assert WorklistItem().patient_id == ""
    assert WorklistItem(patient_id="P1").patient_id == "P1"
    assert WorklistItem(patient_name="N").patient_name == "N"
    w = WorklistItem(patient_id="P1", patient_name="N", raw={"k": "v"})
    assert w.raw["k"] == "v"


def test_echo_none():
    s, c = echo(None)
    assert s is None
    assert c == "Association not established"


def test_store_none():
    s, c = store(None, None)
    assert s is None
    assert c == "Association not established"


def test_dump_info():
    ds = DCM()
    ds.PatientName = "TEST^PATIENT"
    ds.PatientID = "P001"
    info = dump_info(ds)
    assert "TEST^PATIENT" in info
    assert "P001" in info

    ds2 = DCM()
    fm = FileMetaDataset()
    fm.TransferSyntaxUID = "1.2.840.10008.1.2"
    ds2.file_meta = fm
    info2 = dump_info(ds2)
    assert "1.2.840.10008.1.2" in info2


def test_set_patient_info():
    ds = DCM()
    r = set_patient_info(ds, patient_name="NEW", patient_id="P999")
    assert r.PatientName == "NEW"
    assert r.PatientID == "P999"

    ds2 = DCM()
    r2 = set_patient_info(ds2, patient_name="A", patient_id="")
    assert r2.PatientName == "A"
    assert not hasattr(r2, "AccessionNumber")


def test_jpg_to_dicom():
    buf = io.BytesIO()
    Image.new("RGB", (4, 4), color="red").save(buf, format="JPEG")
    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as f:
        f.write(buf.getvalue())
        tmp = f.name
    try:
        ds = jpg_to_dicom(tmp, patient_name="TEST", patient_id="T1")
        assert ds.PatientName == "TEST"
        assert ds.PatientID == "T1"
        assert ds.Rows == 4
        assert ds.Columns == 4
        assert ds.Modality == "XC"
        assert ds.BitsAllocated == 8
        assert ds.SOPClassUID == "1.2.840.10008.5.1.4.1.1.7"
    finally:
        os.unlink(tmp)


def test_jpg_to_dicom_rgba():
    buf = io.BytesIO()
    Image.new("RGBA", (2, 2), color="blue").save(buf, format="PNG")
    with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as f:
        f.write(buf.getvalue())
        tmp = f.name
    try:
        ds = jpg_to_dicom(tmp)
        assert ds.Rows == 2
        assert ds.Columns == 2
    finally:
        os.unlink(tmp)


def test_build_query():
    q = build_query()
    assert q.QueryRetrieveLevel == "STUDY"
    assert q.PatientID == ""
    assert q.PatientName == ""
    q2 = build_query(patient_id="P1", patient_name="N")
    assert q2.PatientID == "P1"
    assert q2.PatientName == "N"


def test_parse_response():
    rds = DCM()
    rds.PatientID = "P1"
    rds.PatientName = "N"
    rds.StudyInstanceUID = "1.2.3"
    rds.AccessionNumber = "A1"
    rds.StudyDate = "20260709"
    rds.StudyDescription = "desc"
    item = parse_response(rds)
    assert item.patient_id == "P1"
    assert item.accession_number == "A1"
    assert item.study_instance_uid == "1.2.3"

    item2 = parse_response(DCM())
    assert item2.patient_id == ""


def test_parse_response_bytes():
    rds = DCM()
    rds.PatientName = b"BYTES^NAME"
    item = parse_response(rds)
    assert "BYTES" in item.patient_name


def test_build_mwl_query():
    q = build_mwl_query(ae_title="TESTAE")
    assert q.ScheduledStationAETitle == "TESTAE"
    assert q.PatientName == ""


def test_parse_mwl_response():
    rds = DCM()
    rds.PatientID = "P1"
    item = parse_mwl_response(rds)
    assert item.patient_id == "P1"

    item2 = parse_mwl_response(DCM())
    assert item2.patient_id == ""


def test_query_mwl_local():
    items = query_mwl_local("SIMULATOR")
    assert len(items) == 5
    assert items[0].patient_name == "BUDI^SUSANTO"
    assert items[0].patient_id == "PAT001"

    items2 = query_mwl_local("NONEXISTENT")
    assert items2 == []


def test_create_ae():
    ae = create_ae("SIMULATOR")
    assert ae.ae_title == "SIMULATOR"
    assert ae.network_timeout == 10
    assert ae.acse_timeout == 10


def test_retrieve_status_code():
    d = DCM()
    d.add_new(0x00000900, "US", 0xA801)
    assert _status_code(d) == 0xA801
    assert _status_code(0) == 0
    assert _status_code(0x0000) == 0
    assert _status_code("x") == 0xFFFF
    assert _status_code("123") == 123


def test_mpps_status_code():
    d = DCM()
    d.add_new(0x00000900, "US", 0x0110)
    assert mpps_status(d) == 0x0110
    assert mpps_status(0x0000) == 0
    assert mpps_status("x") == 0xFFFF


def test_stgcmt_handler_success():
    events = []
    handler = stgcmt_make_handler(on_result=lambda c: events.append(c))

    class MockEvent:
        event_type = 1

        class Request:
            @staticmethod
            def get(key, default=None):
                if key == "ReferencedSOPSequence":
                    return [1, 2, 3]
                return default
        request = Request()

    assert handler(MockEvent()) == 0x0000
    assert events == [3]


def test_stgcmt_handler_failure():
    events = []
    handler = stgcmt_make_handler(on_fail=lambda c: events.append(c))

    class MockEvent:
        event_type = 2

        class Request:
            @staticmethod
            def get(key, default=None):
                if key == "FailedSOPSequence":
                    return [1]
                return default
        request = Request()

    assert handler(MockEvent()) == 0x0000
    assert events == [1]


def test_stgcmt_handler_no_seq():
    handler = stgcmt_make_handler(on_result=lambda c: None)

    class MockEvent:
        event_type = 1

        class Request:
            @staticmethod
            def get(key, default=None):
                return None
        request = Request()

    assert handler(MockEvent()) == 0x0000


if __name__ == "__main__":
    for name, fn in sorted(
        [(k, v) for k, v in globals().items() if k.startswith("test_")]
    ):
        try:
            fn()
            print(f"  ✓ {name}")
        except Exception as e:
            print(f"  ✗ {name}: {e}")
            import traceback
            traceback.print_exc()
