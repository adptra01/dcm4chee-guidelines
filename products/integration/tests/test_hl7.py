"""HL7 v2 — parsing, transform, ORM generation (tanpa jaringan RIS)."""
from app import hl7

ADT = ("MSH|^~\\&|SIMRS|RSUD|RIS|RSUD|20260805120000||ADT^A01|MSG001|P|2.3\r"
       "PID|1||MRN-HL7-1||Joko^Widodo||1980-01-15|M\r")


def test_parse_segments():
    parsed = hl7.parse(ADT)
    assert "MSH" in parsed and "PID" in parsed
    assert parsed["MSH"][0][8] == "ADT^A01"


def test_extract_patient():
    p = hl7.extract_patient(hl7.parse(ADT))
    assert p["patient_id"] == "MRN-HL7-1"
    assert p["name"] == "Joko^Widodo"
    assert p["sex"] == "M"
    assert p["birthdate"] == "1980-01-15"


def test_adt_ack_rejects_empty():
    # tanpa MRN → AR
    ack = hl7.adt_ack("MSH|^~\\&|SIMRS|RSUD|RIS|RSUD|20260805120000||ADT^A01|M2|P|2.3\rPID|1|||||||\r")
    assert "MSA|AR|" in ack


def test_orm_order_generation():
    order = {"order": {"order_no": "ORD-001", "modality": "DX", "status": "scheduled",
                       "requested_at": "2026-08-05T10:00:00",
                       "patient": {"patient_id": "MRN001", "name": "Budi Santoso", "sex": "M"}}}
    msg = hl7.orm_order(order)
    assert msg.startswith("MSH|^~\\&|RIS|RSUD|SIMRS")
    assert "ORM^O01" in msg
    assert "PID|1||MRN001||Budi^Santoso" in msg
    assert "OBR|1|ORD-001||DX^RADIOLOGI" in msg
