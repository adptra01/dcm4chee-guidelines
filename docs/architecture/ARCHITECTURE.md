# Arsitektur Open Radiology Platform (ORP) — v1.0

Ringkasan arsitektur. Keputusan detail ada di `docs/adr/` (ADR-001 s/d ADR-005).

## Diagram

```text
                SATUSEHAT / BPJS / SIMRS / OpenEMR
                        │  REST / HL7 / FHIR (adapter)
                        ▼
┌──────────────────────────────────────────────────────┐
│            Integration Platform (FastAPI)             │  products/integration
│   Adapter · REST · HL7 · FHIR · Webhook · MWL · MPPS  │  Source of truth: komunikasi lintas sistem
└───────┬──────────────────────────────┬───────────────┘
        │ MWL C-FIND (:4243)            │ MPPS N-CREATE/N-SET (:4244)
        │ (perantara RIS↔OMC worklist)  │ modalitas→Integration→RIS(status)
        ▼                              ▼
┌───────────────────────────────────────────────────────┐
│            Laravel RIS (DDEV)                         │  products/ris
│   Patient · Order · Report · Audit                    │  Source of truth: data administratif & klinis
└───────────────────┬───────────────────────────────────┘
                    │ REST (worklist via Integration, bukan langsung ke OMC)
                    ▼
┌───────────────────────────────────────────────────────┐
│    OMC API (FastAPI) + OMC Console (Svelte)           │  products/omc
│   Import · Preview · Queue · C-STORE                  │  Source of truth: antrean transmisi
└───────┬──────────────────────────────┬───────────────┘
        │ REST + DICOM (C-STORE)        │ AI request ②
        ▼                               ▼
┌──────────────────────────┐     ┌──────────────────────────┐
│   Orthanc PACS (Docker)  │     │      AI Platform          │  products/ai
│  Storage · DICOMweb      │     │  Inference · Overlay       │  Source of truth: hasil AI
│  Source of truth: GAMBAR │     └────────────┬─────────────┘
└──────────┬───────────────┘                  │ overlay / measurement
           ▼                                  ▼
   OHIF Viewer                    Radiografer / Radiolog
   (products/viewer)              (menyimak studi dari Orthanc)
```

**Keterangan diagram:**
- **Integration = perantara WAJIB** untuk worklist (MWL :4243) & status prosedur (MPPS :4244) — RIS tidak bicara langsung ke OMC untuk worklist (sesuai `prd-integration.md`: Integration pemilik dua SCP tersebut).
- **AI Platform** terpisah dari OMC (ADR-006 bagian 1): OMC tidak menyimpan hasil AI.

## Source of Truth (ADR-001)

| Data | Pemilik |
|---|---|
| Patient, Order, Report, Audit | **Laravel RIS** |
| DICOM Image | **Orthanc PACS** (Core Rule) |
| Antrean transmisi (queue) | **OMC (FastAPI)** |
| Komunikasi lintas sistem (MWL/MPPS/adapter) | **Integration Platform** |
| Hasil AI (statistik, overlay, measurement, finding) | **AI Platform** |

## Modul (produk)

| Modul | Folder | Tanggung jawab | Source of truth untuk |
|---|---|---|---|
| **RIS** | `products/ris` | Pasien, dokter, prosedur, order, laporan, dashboard | Data administratif & klinis |
| **Integration Platform** | `products/integration` | Adapter SIMRS, HL7 v2, FHIR R4, webhook, MWL SCP :4243, MPPS SCP :4244, auth eksternal | Komunikasi lintas sistem |
| **OMC** | `products/omc` | Import DICOM, preview, queue, C-STORE ke Orthanc | Antrean transmisi |
| **PACS (Orthanc)** | `platform/orthanc` | Penyimpanan DICOM permanen, DICOMweb, backup | Gambar DICOM (satu-satunya) |
| **Viewer** | `products/viewer` + `platform/ohif` | Study list, launch studi, annotation | — (baca dari Orthanc) |
| **AI Platform** | `products/ai` | Inference dari studi Orthanc, overlay | Hasil AI |
| **Developer Platform** | `products/developer-portal` | Dokumentasi, OpenAPI, Postman, CLI | — |

> `packages/*` (dicom-core, workflow-core, report-core, integration-core, shared) **bukan modul** — library yang dipakai `products/*`, sesuai aturan dependency ADR-002.

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
① Worklist: RIS → Integration (MWL C-FIND :4243) → OMC worklist     (Integration = perantara wajib)
② Hasil:    OMC → (C-STORE) → Orthanc ──event──► OMC queue (stored)
③ Status:   modalitas → Integration (MPPS :4244) → RIS (satu-satunya penulis eksternal, ADR-006)
```

> Aturan kepemilikan status (ADR-006 bagian 2): RIS = source of truth status order. Penulis status hanya dua: UI petugas (Volt) & MPPS via Integration. **OMC worker TIDAK menulis status order** — setelah C-STORE ia hanya menandai queue lokal `stored`.

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
