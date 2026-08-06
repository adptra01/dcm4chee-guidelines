"""MPPS — transformasi dataset → status RIS (tanpa server DICOM)."""
from pydicom.dataset import Dataset

from app import mpps


def test_extract_accession_from_spss():
    ds = Dataset()
    ds.ScheduledProcedureStepSequence = [Dataset()]
    ds.ScheduledProcedureStepSequence[0].AccessionNumber = "ORD-001"
    assert mpps._extract_accession(ds) == "ORD-001"


def test_extract_accession_root_fallback():
    ds = Dataset()
    ds.AccessionNumber = "ORD-002"
    assert mpps._extract_accession(ds) == "ORD-002"


def test_extract_accession_empty():
    assert mpps._extract_accession(Dataset()) == ""


def test_handler_ok_tuple():
    assert mpps._handler_ok() == (0x0000, None)
