#!/usr/bin/env python3
"""
Volume test script untuk DCM4CHEE.

Generate studi DICOM sintetis (CT, MR, CR/X-ray, US) dengan ukuran
mendekati studi nyata, lalu kirim ke PACS via C-STORE menggunakan pynetdicom.
Mengukur waktu kirim untuk validasi performa sebelum klaim "siap skala RS".

Dependensi:
    pip install pydicom pynetdicom numpy

Contoh pakai:
    python generate_volume_test.py --host 127.0.0.1 --port 11112 \
        --aet TESTSCU --aec DCM4CHEE --ct 20 --mr 15 --cr 100 --us 30
"""

import argparse
import time
import uuid
from datetime import datetime

import numpy as np
from pydicom.dataset import FileDataset, FileMetaDataset
from pydicom.uid import (
    ExplicitVRLittleEndian,
    CTImageStorage,
    MRImageStorage,
    ComputedRadiographyImageStorage,
    UltrasoundImageStorage,
    generate_uid,
)
from pynetdicom import AE
from pynetdicom.sop_class import (
    CTImageStorage as CT_SOP,
    MRImageStorage as MR_SOP,
    ComputedRadiographyImageStorage as CR_SOP,
    UltrasoundImageStorage as US_SOP,
)

# Konfigurasi ukuran per modalitas (jumlah slice, dimensi matrix)
MODALITY_SPECS = {
    "CT": {"sop_class": CTImageStorage, "sop_uid": CT_SOP, "slices": 150, "rows": 512, "cols": 512},
    "MR": {"sop_class": MRImageStorage, "sop_uid": MR_SOP, "slices": 40, "rows": 256, "cols": 256},
    "CR": {"sop_class": ComputedRadiographyImageStorage, "sop_uid": CR_SOP, "slices": 1, "rows": 2000, "cols": 2500},
    "US": {"sop_class": UltrasoundImageStorage, "sop_uid": US_SOP, "slices": 1, "rows": 600, "cols": 800},
}


def make_synthetic_dataset(modality: str, patient_id: str, study_uid: str, series_uid: str,
                            instance_number: int, rows: int, cols: int, sop_class_uid) -> FileDataset:
    file_meta = FileMetaDataset()
    file_meta.MediaStorageSOPClassUID = sop_class_uid
    file_meta.MediaStorageSOPInstanceUID = generate_uid()
    file_meta.TransferSyntaxUID = ExplicitVRLittleEndian

    ds = FileDataset(None, {}, file_meta=file_meta, preamble=b"\0" * 128)
    ds.PatientName = f"Test^Pasien^{patient_id}"
    ds.PatientID = patient_id
    ds.PatientBirthDate = "19900101"
    ds.PatientSex = "M"

    ds.StudyInstanceUID = study_uid
    ds.SeriesInstanceUID = series_uid
    ds.SOPInstanceUID = file_meta.MediaStorageSOPInstanceUID
    ds.SOPClassUID = sop_class_uid

    ds.Modality = modality
    ds.StudyDate = datetime.now().strftime("%Y%m%d")
    ds.StudyTime = datetime.now().strftime("%H%M%S")
    ds.AccessionNumber = str(uuid.uuid4())[:16]
    ds.InstanceNumber = instance_number
    ds.SeriesNumber = 1
    ds.SeriesDescription = f"Volume test {modality}"
    ds.StudyDescription = f"Synthetic volume test study - {modality}"

    ds.Rows = rows
    ds.Columns = cols
    ds.BitsAllocated = 16
    ds.BitsStored = 16
    ds.HighBit = 15
    ds.PixelRepresentation = 0
    ds.SamplesPerPixel = 1
    ds.PhotometricInterpretation = "MONOCHROME2"

    # Data pixel acak (bukan gambar medis nyata, murni untuk uji beban)
    pixel_array = np.random.randint(0, 4096, size=(rows, cols), dtype=np.uint16)
    ds.PixelData = pixel_array.tobytes()

    ds.is_little_endian = True
    ds.is_implicit_VR = False
    return ds


def send_study(ae_title_local, ae_title_remote, host, port, modality, spec, patient_id):
    ae = AE(ae_title=ae_title_local)
    ae.add_requested_context(spec["sop_uid"])

    assoc = ae.associate(host, port, ae_title=ae_title_remote)
    if not assoc.is_established:
        raise RuntimeError(f"Gagal asosiasi DICOM ke {host}:{port}")

    study_uid = generate_uid()
    series_uid = generate_uid()

    sent = 0
    for i in range(1, spec["slices"] + 1):
        ds = make_synthetic_dataset(
            modality, patient_id, study_uid, series_uid, i,
            spec["rows"], spec["cols"], spec["sop_uid"]
        )
        status = assoc.send_c_store(ds)
        if status and status.Status == 0x0000:
            sent += 1
        else:
            print(f"  Peringatan: slice {i} gagal terkirim, status={status}")

    assoc.release()
    return sent


def main():
    parser = argparse.ArgumentParser(description="Volume test DCM4CHEE via C-STORE")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=11112)
    parser.add_argument("--aet", default="VOLTEST")       # AE title lokal (harus terdaftar di DCM4CHEE jika strict)
    parser.add_argument("--aec", default="DCM4CHEE")      # AE title tujuan (PACS)
    parser.add_argument("--ct", type=int, default=0, help="Jumlah studi CT untuk digenerate")
    parser.add_argument("--mr", type=int, default=0, help="Jumlah studi MR")
    parser.add_argument("--cr", type=int, default=0, help="Jumlah studi X-ray (CR)")
    parser.add_argument("--us", type=int, default=0, help="Jumlah studi USG")
    args = parser.parse_args()

    jobs = {"CT": args.ct, "MR": args.mr, "CR": args.cr, "US": args.us}
    total_start = time.time()
    results = []

    for modality, count in jobs.items():
        if count <= 0:
            continue
        spec = MODALITY_SPECS[modality]
        print(f"\n=== Mengirim {count} studi {modality} ({spec['slices']} slice/studi) ===")
        for n in range(1, count + 1):
            patient_id = f"VOLTEST-{modality}-{n:04d}"
            t0 = time.time()
            sent = send_study(args.aet, args.aec, args.host, args.port, modality, spec, patient_id)
            elapsed = time.time() - t0
            print(f"  Studi {n}/{count}: {sent}/{spec['slices']} slice terkirim, {elapsed:.2f}s")
            results.append((modality, n, sent, elapsed))

    total_elapsed = time.time() - total_start
    total_studies = len(results)
    total_slices = sum(r[2] for r in results)

    print("\n=== Ringkasan volume test ===")
    print(f"Total studi terkirim : {total_studies}")
    print(f"Total slice terkirim : {total_slices}")
    print(f"Total waktu          : {total_elapsed:.1f} detik")
    if total_studies > 0:
        print(f"Rata-rata per studi  : {total_elapsed / total_studies:.2f} detik")


if __name__ == "__main__":
    main()
