# Arsitektur Open Radiology Platform (ORP) — v1.0

Ringkasan arsitektur. Keputusan detail ada di `docs/adr/` (ADR-001 s/d ADR-005).

## Diagram

```text
                SATUSEHAT / BPJS / SIMRS / OpenEMR
                        │  REST / HL7 / FHIR (adapter)
                        ▼
┌──────────────────────────────────────────────────────┐
│            Integration Platform (FastAPI)             │  products/integration
│   Adapter · REST · HL7 · FHIR · Webhook · MWL · MPPS  │  Source of truth: interoperabilitas
└───────┬──────────────────────────────┬───────────────┘
        │ MWL C-FIND (:4243)            │ MPPS N-CREATE/N-SET (:4244)
        │ RIS→Integration→modalitas     │ modalitas→Integration→RIS(status)
        ▼                              ▼
┌───────────────────────────────────────────────────────┐
│            Laravel RIS (DDEV)                         │  products/ris
│   Patient · Order · Report · Audit                    │  Source of truth: data klinis
└───────┬──────────────────────────────┬───────────────┘
        │ REST                          │ MWL worklist / status order
        ▼                               ▼
┌───────────────────────────────────────────────────────┐
│    OMC API (FastAPI) + OMC Console (Svelte)           │  products/omc
│   Import · Preview · Queue · DICOM                    │  Source of truth: workflow modality
└───────┬──────────────────────────────┬───────────────┘
        │ REST + DICOM (C-STORE/C-FIND) │ AI request ②
        ▼                               ▼
┌──────────────────────────┐     ┌──────────────────────────┐
│   Orthanc PACS (Docker)  │     │      AI Platform          │  products/ai
│  Storage · DICOMweb      │     │  Inference · Overlay       │  Source of truth: AI result
│  Source of truth: GAMBAR │     └────────────┬─────────────┘
└──────────┬───────────────┘                  │ overlay / measurement
           ▼                                  ▼
   OHIF Viewer                    Radiografer / Radiolog
   (products/viewer)              (menyimak studi dari Orthanc)
```

**Keterangan diagram:**
- **Integration** = layer terpisah: pemilik MWL SCU (:4243) & MPPS SCP (:4244) untuk komunikasi dengan modalitas. Panah MWL: RIS→Integration→modalitas/OMC; Panah MPPS: modalitas→Integration→RIS (update status order).
- **AI Platform** terpisah dari OMC (ADR-006 bagian 1): OMC tidak menyimpan hasil AI.

## Source of Truth (ADR-001)

| Data | Pemilik |
|---|---|
| Patient, Order, Report, Audit | **Laravel RIS** |
| DICOM Image | **Orthanc PACS** (Core Rule) |
| Queue, Worklist cache | **OMC (FastAPI)** |
| AI result (statistik, overlay, measurement, finding) | **AI Platform** |

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
① Order:   RIS → Integration (MWL C-FIND :4243) / REST → OMC worklist
② Hasil:   OMC → (C-STORE) → Orthanc ──event──► OMC queue (stored)
③ Status:  modalitas → Integration (MPPS :4244) → RIS (satu-satunya penulis eksternal, ADR-006)
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
