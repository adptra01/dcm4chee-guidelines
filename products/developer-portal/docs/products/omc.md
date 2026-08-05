# OMC API

Open Modality Console — workstation modality (pengganti PZDR). FastAPI, port **8100**.
Vertical slice: import → queue → preview → C-STORE ke Orthanc.

## Endpoint

| Method | Path | Fungsi |
|---|---|---|
| GET | `/health` | healthcheck |
| POST | `/studies/import` | upload DICOM (multipart `file`) → simpan ke `data/incoming/` → parse metadata |
| GET | `/studies` | isi antrean (in-memory per proses) |
| GET | `/studies/{id}/preview` | preview PNG (window/level dari tag DICOM) |
| POST | `/studies/{id}/store` | C-STORE ke Orthanc (:4242, AE ORTHANC) |

Antrean **in-memory** — hilang saat service restart. Storage file di
`products/omc/api/data/incoming/` (di-ignore git).

## Contoh

```bash
# import
curl -F "file=@studi.dcm" localhost:8100/studies/import
# preview
curl -o preview.png localhost:8100/studies/<study_id>/preview
# store ke Orthanc
curl -X POST localhost:8100/studies/<study_id>/store
```

## Konsol (SvelteKit, port 5173)

- `/` dashboard — count antrean & stored
- `/queue` — tabel studi + tombol store + link preview
- `/viewer?study_id=` — tampil preview PNG

Depend pada `packages/dicom-core` (install via path: `pip install -e ../../../packages/dicom-core`).

## Test

```bash
cd products/omc/api && .venv/bin/python -m pytest
```
