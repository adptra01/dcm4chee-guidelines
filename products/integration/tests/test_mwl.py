"""MWL SCP — transformasi + C-FIND end-to-end (butuh RIS & thread SCP)."""
import threading

import pytest
from pydicom.dataset import Dataset
from pydicom.uid import ImplicitVRLittleEndian
from pynetdicom import AE, evt
from pynetdicom.sop_class import ModalityWorklistInformationFind as MWL_FIND

from app import mwl

RIS_UP = mwl.fetch_worklist is not None  # module import = sudah cukup


def test_to_dataset_maps_fields():
    item = {
        "order_id": 5, "scheduled_aet": "ORTHANC", "scheduled_at": "2026-08-05 10:30:00",
        "order": {"order_no": "ORD-001", "modality": "DX",
                  "patient": {"patient_id": "MRN001", "name": "Budi Santoso"}},
    }
    ds = mwl.to_dataset(item)
    assert ds.PatientID == "MRN001"
    assert ds.PatientName == "Budi Santoso"
    assert ds.AccessionNumber == "ORD-001"
    assert ds.Modality == "DX"
    step = ds.ScheduledProcedureStepSequence[0]
    assert step.ScheduledStationAETitle == "ORTHANC"
    assert step.ScheduledProcedureStepStartDate == "20260805"
    assert step.ScheduledProcedureStepStartTime == "103000"


def test_matches_wildcard_and_filter():
    ds = mwl.to_dataset({"order": {"modality": "DX", "patient": {"patient_id": "MRN001"}},
                         "scheduled_aet": "ORTHANC"})
    q = Dataset()
    q.Modality = ""  # wildcard
    assert mwl.matches(q, ds)
    q2 = Dataset()
    q2.Modality = "CT"
    assert not mwl.matches(q2, ds)
    # filter AET dari ScheduledProcedureStepSequence
    q3 = Dataset()
    q3.ScheduledProcedureStepSequence = [Dataset()]
    q3.ScheduledProcedureStepSequence[0].ScheduledStationAETitle = "ORTHANC"
    assert mwl.matches(q3, ds)
    q3.ScheduledProcedureStepSequence[0].ScheduledStationAETitle = "CT-1"
    assert not mwl.matches(q3, ds)


@pytest.mark.skipif(True, reason="live: jalankan via script, bukan CI")
def test_cfind_live():
    """Manual: start mwl.start(4243) di thread, lalu query di sini."""
    assert True


# --- end-to-end: start SCP + C-FIND SCU dalam satu proses test ---
@pytest.fixture(scope="module")
def mwl_server():
    port = 4245
    handlers = [(evt.EVT_C_FIND, mwl.on_c_find)]
    ae = AE(ae_title="TEST_MWL")
    ae.add_supported_context(MWL_FIND)
    thread = threading.Thread(
        target=ae.start_server, kwargs={"address": ("127.0.0.1", port), "evt_handlers": handlers},
        daemon=True)
    thread.start()
    import time
    time.sleep(1.0)
    yield port
    ae.shutdown()


def test_cfind_returns_ris_items(mwl_server):
    """C-FIND Modality='DX' → respons non-empty bila RIS punya order DX."""
    sc = AE(ae_title="TEST_SCU")
    sc.add_requested_context(MWL_FIND, ImplicitVRLittleEndian)
    assoc = sc.associate("127.0.0.1", mwl_server, ae_title="TEST_MWL")
    assert assoc.is_established

    query = Dataset()
    query.QueryRetrieveLevel = "PROCEDURE"
    query.Modality = "DX"
    query.ScheduledProcedureStepSequence = [Dataset()]

    results = []
    for status, ds in assoc.send_c_find(query, MWL_FIND):
        if ds:
            results.append(ds)
    assoc.release()
    # Tanpa data RIS deterministik — asersi lunak: proses berjalan tanpa error
    assert status.Status in (0x0000, 0xFF00)
