import tkinter as tk
from tkinter import ttk


class PatientDetailFrame(ttk.LabelFrame):
    def __init__(self, parent):
        super().__init__(parent, text="Patient / Study Detail", padding=8)
        self._create_widgets()

    def _create_widgets(self):
        fields = [
            ("Patient Name:", "patient_name"),
            ("Patient ID:", "patient_id"),
            ("Accession:", "accession"),
            ("Study Desc:", "study_desc"),
            ("Modality:", "modality"),
            ("Date:", "study_date"),
            ("Req. Procedure:", "req_proc"),
            ("Scheduled SPS:", "sps_desc"),
            ("Starts:", "sps_start"),
            ("Station AE:", "station_ae"),
        ]
        self._labels = {}
        for i, (label, key) in enumerate(fields):
            ttk.Label(self, text=label, style="TLabelBold.TLabel").grid(
                row=i, column=0, sticky=tk.W, padx=4, pady=1)
            lbl = ttk.Label(self, text="-")
            lbl.grid(row=i, column=1, sticky=tk.W, padx=4, pady=1)
            self._labels[key] = lbl

    def set_patient(self, item):
        self._labels["patient_name"].configure(text=item.patient_name)
        self._labels["patient_id"].configure(text=item.patient_id)
        self._labels["accession"].configure(text=item.accession_number)
        self._labels["study_desc"].configure(
            text=item.study_description or item.requested_procedure_description or "-")
        self._labels["modality"].configure(text=item.modality or "-")
        date_str = item.study_date or item.scheduled_procedure_step_start_date or "-"
        time_str = item.study_time or item.scheduled_procedure_step_start_time or ""
        self._labels["study_date"].configure(text=f"{date_str} {time_str}".strip())
        self._labels["req_proc"].configure(text=item.requested_procedure_description or "-")
        sps = item.study_description or ""
        self._labels["sps_desc"].configure(text=sps or "-")
        start = ""
        if item.scheduled_procedure_step_start_date:
            start = item.scheduled_procedure_step_start_date
            if item.scheduled_procedure_step_start_time:
                start += f" {item.scheduled_procedure_step_start_time}"
        self._labels["sps_start"].configure(text=start or "-")
        self._labels["station_ae"].configure(
            text=item.scheduled_station_ae_title or item.station_name or "-")

    def clear(self):
        for lbl in self._labels.values():
            lbl.configure(text="-")
