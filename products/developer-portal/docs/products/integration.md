# Integration Service

Adapter SIMRS eksternal — **penerjemah kontrak, bukan penyimpan data**.
FastAPI, port **8300**. Empat protokol: MORBIS, MWL SCP (DICOM), FHIR (di RIS), HL7 v2.

## Auth

Endpoint eksternal (MORBIS, HL7) diproteksi **API key** via header `X-API-Key`.
Aktif hanya bila `API_KEYS` diset di `.env` (koma-terpisah) — kosong = nonaktif
(dev). **Set di produksi!**

```bash
curl -X POST localhost:8300/morbis/sep \
  -H "X-API-Key: <key>" -H "Content-Type: application/json" \
  -d '{"no_kartu": "0001234567890"}'
```

## MORBIS (BPJS VClaim)

| Method | Path | Fungsi |
|---|---|---|
| POST | `/morbis/sep` | buat SEP (`no_kartu`, `tanggal`) |
| POST | `/morbis/claim` | kirim klaim (`no_sep`, `no_kartu`, `biaya`) |
| GET | `/morbis/mode` | mode aktif (mock/real) |

Mode **mock** default (tanpa kredensial, data contoh). Real: set di `.env`
(`MORBIS_MODE=real` + `CONS_ID`/`SECRET`/`USER_KEY`). Kredensial **tidak pernah
di-hardcode**; signature HMAC SHA256 (`X-cons-id`/`X-timestamp`/`X-signature`).

## HL7 v2

| Method | Path | Fungsi |
|---|---|---|
| POST | `/hl7/message` | terima ADT^A01 → buat pasien di RIS → ACK (MSA AA/AR) |

Parser pipe-delimited (MSH/PID/ORC/OBR) tanpa library. `ORM-O01` bisa
di-generate dari order RIS via `app.hl7.orm_order()`.

```bash
curl -X POST localhost:8300/hl7/message -H "Content-Type: application/json" -d '{
  "message": "MSH|^~\\&|SIMRS|RSUD|RIS|RSUD|20260805120000||ADT^A01|M1|P|2.3\rPID|1||MRN001||Budi^Santoso||1985-06-12|M\r"}'
```

## MWL SCP (DICOM C-FIND)

SCP pynetdicom — **AE `MWL_SCP`, port 4243**. Modality query jadwal dari
RIS `/api/worklist` via C-FIND (Basic Worklist Management).

```bash
cd products/integration && .venv/bin/python -c "from app.mwl import start; start()"
# atau dari aplikasi: thread/supervisord
```

Filter: Modality, PatientID, ScheduledStationAETitle (wildcard bila kosong).
Item DICOM: PatientID/Name, AccessionNumber (order_no), SPPS AET/date/time.

## Menjalankan

```bash
cd products/integration && .venv/bin/pip install -e ".[dev]"
.venv/bin/uvicorn app.main:app --port 8300
```

Test: `pytest` (11 passed; MWL e2e butuh RIS hidup).
