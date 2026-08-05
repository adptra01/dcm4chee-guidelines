# Arsitektur Open Radiology Platform (ORP) — v1.0

Ringkasan arsitektur. Keputusan detail ada di `docs/adr/` (ADR-001 s/d ADR-005).

## Diagram

```text
                  MORBIS / SIMRS
                       │  REST / HL7 / FHIR
                       ▼
┌───────────────────────────────────────────────┐
│            Laravel RIS (DDEV)                 │  products/ris
│   Patient · Order · Report · Audit            │  Source of truth: data klinis
└───────────────────────┬───────────────────────┘
                        │ REST + JWT
                        ▼
┌───────────────────────────────────────────────┐
│    OMC API (FastAPI) + OMC Console (Svelte)   │  products/omc
│   Import · Preview · Queue · MWL · MPPS · DICOM│  Source of truth: workflow modality
└───────────────────────┬───────────────────────┘
                        │ REST + DICOM (C-STORE/C-FIND/MWL/MPPS)
                        ▼
┌───────────────────────────────────────────────┐
│        Orthanc PACS (Docker)                  │  platform/orthanc
│   Storage · DICOMweb · REST · PostgreSQL      │  Source of truth: GAMBAR (Core Rule)
└──────────┬──────────────────────┬─────────────┘
           ▼                      ▼
   OHIF Viewer                OMC Console
   (products/viewer)          (/viewer/:studyUID)
           │
           ▼
   Radiografer / Radiolog
```

## Source of Truth (ADR-001)

| Data | Pemilik |
|---|---|
| Patient, Order, Report, Audit | **Laravel RIS** |
| DICOM Image | **Orthanc PACS** |
| Queue, Worklist cache, AI result | **OMC (FastAPI)** |

## Core Rule (tidak bisa dinegosiasi)

> **Orthanc satu-satunya yang menyimpan & melayani DICOM.** (ADR-003)

## State Machine Workflow

```
Order Created → Waiting → Worklist → In Progress
→ Images Acquired → Queued → Stored → Reading
→ Reported → Verified → Published
```

- `Stored` = gambar tiba di Orthanc (event → OMC worker update queue).
- `Reading`+ = sisi radiolog (Laravel + OHIF).

## Dua Alur (Business ≠ DICOM)

```
① Order:   RIS → (REST) → OMC worklist      (MWL C-FIND / REST)
② Hasil:   OMC → (C-STORE) → Orthanc ──event──► OMC worker (preview/AI/thumbnail)
                                                     └──► update RIS (status/laporan)
```

## Aturan Dependency (ADR-002)

- `products/*` → boleh pakai `packages/*`
- `packages/*` → TIDAK boleh pakai `products/*`
- `platform/*` → berdiri sendiri (docker services)

## Struktur

```
products/       # domain bisnis (ris, omc, ai, viewer, integration, developer-portal)
packages/       # library reusable (dicom-core, workflow-core, report-core, integration-core, shared)
platform/       # infrastruktur (orthanc, postgres, redis, ohif, gateway, monitoring)
scripts/        # check, health, backup, restore
docs/           # architecture, adr, api, workflow, dicom, deployment
sample-data/    # aset contoh bersama (dicom, json, hl7, fhir, reports, fixtures)
legacy/         # referensi aktif proyek lama (laravel-pacs, dcm4chee)
_archive/       # arsip murni
```

## Lihat juga

- `docs/adr/ADR-001-architecture.md` … `ADR-005-compose-strategy.md`
- `docs/api/` — kontrak API per produk
- `docs/workflow/` — state machine & alur
- `docs/dicom/` — belajar DICOM (kasus lab ini)
- `docs/deployment/` — menjalankan stack
