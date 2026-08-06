"""dicom-core SCU (MWL C-FIND + MPPS) terhadap SCP Integration — end-to-end.

Menguji protokol SCU→SCP nyata: asosiasi, C-FIND MWL, MPPS N-CREATE/N-SET.
SCP berjalan dalam-proses (thread daemon), RIS fetch & MPPS update di-mock.
"""
import sys
import threading
import time
from pathlib import Path

import pytest
from pydicom.dataset import Dataset

sys.path.insert(0, str(Path(__file__).resolve().parents[3] / "packages" / "dicom-core" / "src"))

from dicom_core import mpps_send, mwl_query  # noqa: E402


@pytest.fixture(scope="module")
def mwl_server():
    import app.mwl as mwl

    mwl.fetch_worklist = lambda: [{
        "status": "pending",
        "scheduled_at": "2026-08-05 10:30:00",
        "scheduled_aet": "RIS",
        "order_id": 1,
        "order": {"order_no": "ORD-001", "modality": "CT",
                  "patient": {"patient_id": "MRN1", "name": "TEST"}},
    }]
    threading.Thread(target=mwl.start, daemon=True).start()
    time.sleep(2)
    yield


@pytest.fixture(scope="module")
def mpps_server():
    import app.mpps as mpps

    mpps.update_order_status = lambda *a, **k: True  # jangan sentuh RIS live
    threading.Thread(target=mpps.start, daemon=True).start()
    time.sleep(2)
    yield


def test_mwl_find_returns_worklist(mwl_server):
    results = mwl_query(Dataset(), host="localhost", port=4243,
                        scu_ae="OMC_CONSOLE", scp_ae="MWL_SCP")
    assert len(results) == 1
    assert results[0].AccessionNumber == "ORD-001"
    assert results[0].PatientName == "TEST"


def test_mwl_find_filter_by_patient(mwl_server):
    model = Dataset()
    model.PatientID = "MRN1"
    assert len(mwl_query(model, host="localhost", port=4243,
                         scu_ae="OMC_CONSOLE", scp_ae="MWL_SCP")) == 1

    model2 = Dataset()
    model2.PatientID = "TIDAKADA"
    assert len(mwl_query(model2, host="localhost", port=4243,
                         scu_ae="OMC_CONSOLE", scp_ae="MWL_SCP")) == 0


def _make_mpps() -> Dataset:
    ds = Dataset()
    ds.SpecificCharacterSet = "ISO_IR 192"
    ds.PatientName = "TEST"
    ds.PatientID = "MRN1"
    ds.AccessionNumber = "ORD-001"
    ds.PerformedProcedureStepID = "1"
    ds.PerformedProcedureStepStatus = "IN PROGRESS"
    ds.PerformedStationAETitle = "OMC"
    ds.PerformedProcedureStepStartDate = "20260805"
    ds.PerformedProcedureStepStartTime = "103000"
    return ds


def test_mpps_n_create_in_progress(mpps_server):
    ds = _make_mpps()  # status IN PROGRESS → N-CREATE
    ok = mpps_send(ds, host="localhost", port=4244,
                   scu_ae="OMC_CONSOLE", scp_ae="MPPS_SCP")
    assert ok is True


def test_mpps_n_set_completed(mpps_server):
    ds = _make_mpps()
    ds.PerformedProcedureStepStatus = "COMPLETED"  # → N-SET
    ds.PerformedProcedureStepEndDate = "20260805"
    ds.PerformedProcedureStepEndTime = "103500"
    ok = mpps_send(ds, host="localhost", port=4244,
                   scu_ae="OMC_CONSOLE", scp_ae="MPPS_SCP")
    assert ok is True