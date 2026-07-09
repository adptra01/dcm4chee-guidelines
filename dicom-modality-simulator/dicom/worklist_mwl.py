import json
from pathlib import Path
from pydicom.dataset import Dataset
from pynetdicom.sop_class import ModalityWorklistInformationFind

from models.patient import WorklistItem

MWL_DATA_PATH = Path(__file__).parent.parent / "mwl_data.json"


def build_mwl_query(ae_title="SIMULATOR", patient_id=None, patient_name=None):
    ds = Dataset()
    ds.PatientName = patient_name or ""
    ds.PatientID = patient_id or ""
    ds.AccessionNumber = ""
    ds.Modality = ""
    ds.StudyInstanceUID = ""
    ds.RequestedProcedureDescription = ""
    ds.ScheduledProcedureStepStartDate = ""
    ds.ScheduledProcedureStepStartTime = ""
    ds.ScheduledProcedureStepID = ""
    ds.ScheduledStationAETitle = ae_title
    ds.ScheduledStationName = ""
    return ds


def parse_mwl_response(ds) -> WorklistItem:
    def _val(attr):
        v = getattr(ds, attr, None)
        if v is None:
            return ""
        if isinstance(v, bytes):
            return v.decode("utf-8", "replace")
        return str(v)

    return WorklistItem(
        patient_id=_val("PatientID"),
        patient_name=_val("PatientName"),
        patient_birth_date=_val("PatientBirthDate"),
        patient_sex=_val("PatientSex"),
        study_instance_uid=_val("StudyInstanceUID"),
        accession_number=_val("AccessionNumber"),
        study_date=_val("ScheduledProcedureStepStartDate"),
        study_time=_val("ScheduledProcedureStepStartTime"),
        study_description=_val("RequestedProcedureDescription"),
        modality=_val("Modality"),
        station_name=_val("ScheduledStationName"),
        requested_procedure_description=_val("RequestedProcedureDescription"),
        scheduled_procedure_step_start_date=_val("ScheduledProcedureStepStartDate"),
        scheduled_procedure_step_start_time=_val("ScheduledProcedureStepStartTime"),
        scheduled_station_ae_title=_val("ScheduledStationAETitle"),
        raw=dict(ds.items()),
    )


def query_mwl_dicom(assoc, query_ds) -> list:
    results = []
    responses = assoc.send_c_find(query_ds, query_model=ModalityWorklistInformationFind)
    for status, ds in responses:
        if ds:
            results.append(parse_mwl_response(ds))
    return results


def query_mwl_local(ae_title="SIMULATOR") -> list:
    path = MWL_DATA_PATH
    if not path.exists():
        return []
    try:
        data = json.loads(path.read_text())
    except (json.JSONDecodeError, OSError):
        return []
    results = []
    for entry in data:
        if entry.get("scheduled_station_ae_title", "") == ae_title:
            results.append(WorklistItem(
                patient_id=entry.get("patient_id", ""),
                patient_name=entry.get("patient_name", ""),
                patient_birth_date=entry.get("patient_birth_date", ""),
                patient_sex=entry.get("patient_sex", ""),
                study_instance_uid=entry.get("study_instance_uid", ""),
                accession_number=entry.get("accession_number", ""),
                study_date=entry.get("scheduled_procedure_step_start_date", ""),
                study_time=entry.get("scheduled_procedure_step_start_time", ""),
                study_description=entry.get("study_description", ""),
                modality=entry.get("modality", ""),
                station_name=entry.get("scheduled_station_name", ""),
                requested_procedure_description=entry.get("requested_procedure_description", ""),
                scheduled_procedure_step_start_date=entry.get("scheduled_procedure_step_start_date", ""),
                scheduled_procedure_step_start_time=entry.get("scheduled_procedure_step_start_time", ""),
                scheduled_station_ae_title=entry.get("scheduled_station_ae_title", ""),
                raw=entry,
            ))
    return results
