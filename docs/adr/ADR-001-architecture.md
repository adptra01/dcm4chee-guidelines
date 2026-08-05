# ADR-001 — Arsitektur Open Radiology Platform (ORP)

- Status: **Accepted** (2026-08-05)
- Konteks: mengganti PZDR sebagai modality, membangun platform radiologi
  modular open-source yang dapat berkembang bertahun-tahun (RIS, PACS, viewer,
  AI, integrasi SIMRS).

## Keputusan

Tiga "source of truth" yang jelas, tidak tumpang tindih:

| Komponen | Tanggung jawab | Source of Truth |
|---|---|---|
| **Laravel RIS** (`products/ris`) | Pasien, order, jadwal, laporan, audit | Data klinis & workflow bisnis |
| **Orthanc PACS** (`platform/orthanc`) | Penyimpanan DICOM, Query/Retrieve, DICOMweb | Gambar medis |
| **OMC (FastAPI + Svelte)** (`products/omc`) | Akuisisi/import, worklist, preview, QC, queue, komunikasi DICOM | Workflow teknis modality |

### Core Rule (wajib, tidak bisa dinegosiasi)

> **Orthanc adalah satu-satunya komponen yang menyimpan dan melayani DICOM.**

- ❌ FastAPI tidak menyimpan file DICOM permanen (hanya buffer saat ingest).
- ❌ Laravel tidak menyimpan file DICOM.
- ❌ Svelte tidak mengetahui lokasi file DICOM.
- ✅ Semua gambar selalu berasal dari Orthanc.

### Aturan komunikasi

- **Laravel tidak pernah berbicara DICOM.** Hanya REST + JWT + database.
- Semua DICOM (C-ECHO/C-STORE/MWL/MPPS) berada di OMC (FastAPI) dan Orthanc.
- Tidak ada database sharing, tidak ada polling — event-driven via Orthanc.

## Kontrak komunikasi

| Arah | Protokol | Endpoint |
|---|---|---|
| Laravel → OMC | REST + JWT | `POST /orders`, `POST /patients`, `POST /reports`, `GET /studies` |
| OMC → Laravel | REST + JWT | `POST /study-completed`, `POST /queue-status`, `POST /report-ready` |
| Modality → OMC | DICOM | C-ECHO, C-STORE, MWL (C-FIND), MPPS |
| OMC → Orthanc | REST | store/query/retrieve via Orthanc REST API |

## Konsekuensi

- POSITIF: setiap komponen independen; PACS bisa diganti (ADR-004); modality
  baru tinggal pasang adapter; AI membaca dari Orthanc.
- NEGATIF: lapisan integrasi REST antar produk diperlukan sejak awal.

## Lihat juga

- ADR-002 (monorepo), ADR-003 (DICOM storage), ADR-004 (adapter), ADR-005 (compose)
