# AI Service

Inferensi & CAD untuk citra radiologi. FastAPI, port **8200**.
Membaca instance langsung dari Orthanc REST, memberi saran — **saran, bukan modifikasi gambar**.

## Endpoint

| Method | Path | Fungsi |
|---|---|---|
| GET | `/health` | healthcheck |
| POST | `/analyze/instance/{orthanc_id}` | statistik pixel + finding (404 bila instance tak ada) |
| GET | `/analyze/series/{series_id}` | analisis semua instance dalam series |

## Engine

v1 = **statistik** (bukan ML — jujur): decode pixel → apply VOI LUT →
mean/std/persentil hyperdens → heuristik finding. Model ML menggantikan di
milestone berikutnya; output statistik menjadi input model.

```json
{
  "orthanc_id": "3b1dd0d6-...",
  "rows": 256, "columns": 256,
  "mean_voi": 42.56, "std_voi": 18895.26,
  "pct_hyperdense": 0.1,
  "finding": "Distribusi densitas normal",
  "engine": "statistik-v1"
}
```

## Menjalankan

```bash
cd products/ai/api && .venv/bin/pip install -e ".[dev]"
.venv/bin/uvicorn app.main:app --port 8200
```

Test: `pytest` — uji live memerlukan Orthanc hidup (skip otomatis bila mati).
