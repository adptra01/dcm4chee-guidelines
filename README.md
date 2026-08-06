# Open Radiology Platform (ORP)

Platform radiologi modular & open-source: RIS (Laravel), Open Modality Console
(pengganti PZDR, FastAPI + Svelte), PACS (Orthanc), Viewer (OHIF), AI, dan
integrasi SIMRS. Dibangun bertahap (lihat Milestone).

## Arsitektur (ringkas)

```
SIMRS/MORBIS ──► Laravel RIS (products/ris) ──REST──► OMC (products/omc) ──DICOM──► Orthanc PACS
                                                                                       │
                                                                        OHIF Viewer / OMC Console
```

Detail: `docs/architecture/ARCHITECTURE.md` • Keputusan: `docs/adr/`

## ⛔ Core Rule (tidak bisa dinegosiasi — ADR-001/003)

> **Orthanc adalah satu-satunya komponen yang menyimpan dan melayani DICOM.**
>
> ❌ FastAPI tidak menyimpan DICOM permanen
> ❌ Laravel tidak menyimpan DICOM
> ❌ Svelte tidak mengetahui lokasi file DICOM
> ✅ Semua gambar selalu berasal dari Orthanc

## Struktur

```
products/       # domain bisnis — ris, omc (api+console), ai, viewer, integration, developer-portal
packages/       # library reusable — dicom-core, workflow-core, report-core, integration-core, shared
platform/       # infrastruktur — orthanc, postgres, redis, ohif, gateway, monitoring
scripts/        # check, health, backup, restore
docs/           # architecture, adr, api, workflow, dicom, deployment
sample-data/    # aset contoh bersama (dicom, json, hl7, fhir, reports, fixtures)
tests/          # test bersama
legacy/         # referensi aktif proyek lama (laravel-pacs, dcm4chee)
_archive/       # arsip murni (tidak di-git)
devtools/       # utilitas pengembangan (bukan library, bukan skrip operasional)
```

## Quick Start

```bash
# 1. Infrastruktur platform (Orthanc + Postgres + OHIF)
cp .env.example .env
docker compose up -d

# 2. Cek kesehatan
./scripts/check.sh

# 3. RIS (Laravel via DDEV)
cd products/ris && ddev start

# 4. OMC API (FastAPI) — lihat produk masing-masing
cd products/omc/api && docker compose up
```

## Endpoint Platform (live)

| URL | Fungsi |
|---|---|
| `http://<host>:3000/` | OHIF Viewer |
| `http://<host>:8042/` | Orthanc UI (307 → UI = normal) |
| `http://<host>:8042/dicom-web/studies` | DICOMweb QIDO-RS |
| `<host>:4242` (AE `ORTHANC`) | Port DICOM (modalitas) |

`<host>` = `localhost` / `10.205.136.1` (lihat `docs/deployment/ACCESS-JARINGAN.md`).

## Backup & Restore

```bash
./scripts/backup.sh                        # storage + index → data/backups/
./scripts/restore.sh                       # ⛔ butuh --run (destruktif, manual)
./scripts/check.sh                         # verifikasi setelah restore
```

## Milestone

| MS | Konten | Status |
|---|---|---|
| MS0-W1 | Foundation monorepo (struktur, platform, scripts, docs, ADR) | ✅ `12117a2` |
| MS0-W2 | Semua produk bootable (`GET /health`, `ddev start`, `npm run dev`) | ✅ `3826653` |
| MS1 | `packages/dicom-core` (parser, preview, echo/store) | ✅ (parse/preview/echo/store) |
| MS2 | OMC API vertical slice (import → preview → queue → Orthanc) | ✅ `f978fd33` |
| MS3 | RIS: patient, order, worklist | ✅ (API pasien/order/worklist) |
| MS4 | OMC Console: dashboard, queue, viewer | ✅ (3 halaman live) |
| MS5 | Reporting: laporan radiologi per order (RIS) | ✅ (reports API) |
| MS6 | Integrasi MORBIS: SEP + klaim BPJS (mock/real) | ✅ (mock live) |
| MS7 | AI service: analisis statistik dari Orthanc (engine v1, ML di MS8+) | ✅ (live) |
| MS8 | FHIR R4: RIS expose Patient/ServiceRequest/DiagnosticReport | ✅ (live) |
| MS9 | MWL SCP: modality query jadwal RIS (C-FIND, DICOM 4243) | ✅ (live) |
| MS10 | HL7 v2: ADT-A01 → RIS patient (ACK), ORM-O01 generate | ✅ (live) |
| MS11 | Developer portal: docs per produk (API, integrasi, cara jalankan) | ✅ (8 halaman) |
| MS12 | Enterprise: API key auth untuk endpoint eksternal (integration) | ✅ (401 pasif) |
| MS13 | MPPS SCP: modality → status order RIS (N-CREATE/N-SET) | ✅ (live 0x0) |
| MS14+ | Model ML, multi-tenant | ⏳ roadmap |

## Panduan Terkait

- `docs/dicom/DICOM-BELAJAR.md` — belajar DICOM dari kasus lab
- `docs/deployment/INSTALL-PZDR.md` — konfigurasi PZDR (modalitas eksternal)
- `docs/deployment/TROUBLESHOOTING.md` — diagnosa masalah
- `docs/adr/` — keputusan arsitektur (5 ADR)
