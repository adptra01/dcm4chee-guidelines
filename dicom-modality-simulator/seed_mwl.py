import json
import random
from pathlib import Path
from datetime import datetime, timedelta

MWL_PATH = Path(__file__).parent / "mwl_data.json"

PATIENTS = [
    {"name": "BUDI^SUSANTO", "pid": "PAT001", "dob": "19800315", "sex": "M", "acc": "ACC001", "mod": "CT", "proc": "CT ABDOMEN", "sps": "CT ABDOMEN WITH CONTRAST", "desc": "Abdomen CT"},
    {"name": "SITI^RAHMAWATI", "pid": "PAT002", "dob": "19920720", "sex": "F", "acc": "ACC002", "mod": "MR", "proc": "MRI BRAIN", "sps": "BRAIN MRI WITHOUT CONTRAST", "desc": "Brain MRI"},
    {"name": "AGUS^PRAMONO", "pid": "PAT003", "dob": "19651110", "sex": "M", "acc": "ACC003", "mod": "CR", "proc": "CHEST XRAY", "sps": "CHEST PA/LATERAL", "desc": "Chest X-Ray"},
    {"name": "DEWI^KUSUMA", "pid": "PAT004", "dob": "19780805", "sex": "F", "acc": "ACC004", "mod": "US", "proc": "US ABDOMEN", "sps": "ABDOMEN ULTRASOUND", "desc": "Abdomen US"},
    {"name": "HARI^PRASETYA", "pid": "PAT005", "dob": "20010330", "sex": "M", "acc": "ACC005", "mod": "XA", "proc": "CORONARY ANGIO", "sps": "CORONARY ANGIOGRAPHY", "desc": "Coronary Angiography"},
]

def generate():
    today = datetime.now().strftime("%Y%m%d")
    items = []
    for p in PATIENTS:
        uid = f"1.2.840.10008.5.1.4.1.2.3.4.5.6.7.{random.randint(100, 999)}"
        items.append({
            "patient_name": p["name"],
            "patient_id": p["pid"],
            "patient_birth_date": p["dob"],
            "patient_sex": p["sex"],
            "accession_number": p["acc"],
            "modality": p["mod"],
            "study_description": p["desc"],
            "study_instance_uid": uid,
            "study_date": today,
            "study_time": f"{random.randint(8, 16):02d}{random.randint(0, 59):02d}",
            "requested_procedure_description": p["proc"],
            "requested_procedure_id": f"REQ{p['pid'][-3:]}",
            "scheduled_procedure_step_id": f"SPS{p['pid'][-3:]}",
            "scheduled_procedure_step_description": p["sps"],
            "scheduled_procedure_step_start_date": today,
            "scheduled_procedure_step_start_time": f"{random.randint(8, 16):02d}{random.randint(0, 59):02d}",
            "scheduled_station_ae_title": "SIMULATOR",
            "scheduled_station_name": "SIMULATOR",
        })

    MWL_PATH.write_text(json.dumps(items, indent=2))
    print(f"Generated {len(items)} MWL entries -> {MWL_PATH}")
    for item in items:
        print(f"  {item['patient_id']:8s} {item['patient_name']:20s} {item['modality']:4s} {item['accession_number']}")

if __name__ == "__main__":
    generate()
