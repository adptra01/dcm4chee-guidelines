"""Analisis citra DICOM dari Orthanc — v1 statistik (model ML menyusul di MS8).

Pipeline: ambil instance DICOM dari Orthanc REST → decode pixel →
statistik dasar + deteksi sederhana. Jujur: ini bukan AI ML, ini dasar
yang nantinya jadi input model.
"""
from __future__ import annotations

import io
import json
from urllib.error import HTTPError
from urllib.request import urlopen

import numpy as np
import pydicom
try:
    from pydicom.pixels.processing import apply_voi_lut
except ImportError:  # pydicom < 3.0
    from pydicom.pixel_data_handlers.util import apply_voi_lut

ORTHANC = "http://localhost:8042"


def fetch_instance(orthanc_id: str) -> bytes:
    """Ambil file DICOM mentah dari Orthanc REST."""
    try:
        with urlopen(f"{ORTHANC}/instances/{orthanc_id}/file") as r:
            return r.read()
    except HTTPError as e:
        raise ValueError(f"instance {orthanc_id} tidak ada di Orthanc ({e.code})") from e


def _fetch_json(path: str):
    with urlopen(f"{ORTHANC}{path}") as r:
        return json.loads(r.read())


def analyze_series(series_id: str) -> dict:
    """Analisis semua instance dalam satu series (Orthanc REST)."""
    try:
        data = _fetch_json(f"/series/{series_id}/instances")
    except HTTPError as e:
        raise ValueError(f"series {series_id} tidak ada di Orthanc ({e.code})") from e
    # Orthanc v2: list objek (ID di key 'ID'); fallback list string
    ids = [d["ID"] if isinstance(d, dict) else d for d in data]
    results = [analyze_instance(i) for i in ids]
    return {"series_id": series_id, "count": len(results), "instances": results}


def analyze_instance(orthanc_id: str) -> dict:
    """Statistik pixel + nilai VOI LUT dari satu instance."""
    raw = fetch_instance(orthanc_id)
    ds = pydicom.dcmread(io.BytesIO(raw))
    if not hasattr(ds, "pixel_array"):
        return {"orthanc_id": orthanc_id, "error": "tidak ada pixel data"}

    arr = np.asarray(ds.pixel_array)
    if arr.size == 0:
        return {"orthanc_id": orthanc_id, "error": "pixel kosong"}

    voi = apply_voi_lut(arr, ds)  # window/level DICOM → skala nyata
    mean = float(np.mean(voi))
    std = float(np.std(voi))
    pct_high = float(np.mean(voi > np.percentile(voi, 90)))

    # Heuristik sederhana (v1; ML menggantikan ini di MS8)
    if std < 5:
        finding = "Kontras sangat rendah — periksa ulang teknik akuisisi"
    elif pct_high > 0.2:
        finding = "Area hiperdens meluas (>20% piksel di persentil 90)"
    else:
        finding = "Distribusi densitas normal"

    return {
        "orthanc_id": orthanc_id,
        "rows": int(arr.shape[0]),
        "columns": int(arr.shape[1]),
        "mean_voi": round(mean, 2),
        "std_voi": round(std, 2),
        "pct_hyperdense": round(pct_high, 4),
        "finding": finding,
        "engine": "statistik-v1",  # ponytail: ML model di MS8
    }
