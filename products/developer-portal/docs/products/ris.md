# RIS API & FHIR

Radiology Information System. Laravel 12 via DDEV (project `ris`, `https://ris.ddev.site`).
MySQL di DDEV; test pakai sqlite in-memory.

## Endpoint RIS

| Method | Path | Fungsi |
|---|---|---|
| GET/POST | `/api/patients` | daftar/buat pasien (`patient_id` MRN unik, `name`, `sex`, `birthdate`) |
| GET/POST | `/api/orders` | daftar/buat order — otomatis membuat slot worklist |
| GET | `/api/worklist` | MWL source: item + order + patient |
| GET/POST | `/api/reports` | laporan radiologi (`order_id`, `radiologist`, `findings`, `impression`, `status`) |

Buat order otomatis membuat `worklist_items` (AET default `RIS`) — sumber data MWL SCP.

## FHIR R4 (SATUSEHAT-ready)

Content-Type `application/fhir+json`.

| Method | Path | Resource |
|---|---|---|
| GET | `/api/fhir/Patient/{id}` | Patient (identifier NIK system) |
| GET | `/api/fhir/Patient?identifier=&name=` | Bundle searchset |
| GET | `/api/fhir/ServiceRequest/{id}` | Order → status/intent mapping |
| GET | `/api/fhir/DiagnosticReport/{id}` | Report → basedOn/conclusion |

Mapping manual tanpa library (resource yang dipakai terbatas).

## Menjalankan

```bash
cd products/ris/backend
ddev start
ddev artisan migrate
# test: phpunit.xml → sqlite :memory: (tidak menyentuh DB dev)
ddev artisan test
```

## Alur klinis

```
pasien → order → worklist (MWL) → gambar di Orthanc → laporan
```
