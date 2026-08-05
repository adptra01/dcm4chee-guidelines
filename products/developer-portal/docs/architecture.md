# Arsitektur

Ringkasan. Detail: `docs/architecture/ARCHITECTURE.md` dan `docs/adr/` di root repo.

```text
SIMRS/MORBIS ──► Laravel RIS ──REST──► OMC (FastAPI+Svelte) ──DICOM──► Orthanc PACS
                                                                           │
                                                          OHIF / OMC Console
```

## Source of Truth

| Data | Pemilik |
|---|---|
| Patient, Order, Report, Audit | Laravel RIS |
| DICOM Image | Orthanc PACS |
| Queue, Worklist, AI result | OMC |

## Core Rule

> **Orthanc satu-satunya yang menyimpan & melayani DICOM.**
> Laravel/FastAPI/Svelte tidak menyimpan DICOM permanen.

## Struktur

```
products/       domain bisnis (ris, omc, ai, viewer, integration)
packages/       library reusable (tidak boleh depend ke products/)
platform/       infrastruktur (orthanc, postgres, redis, ohif)
scripts/        check, health, backup, restore
docs/           architecture, adr, api, workflow, dicom, deployment
```

## Lihat juga

- ADR-001 s/d ADR-005 di `docs/adr/`
- OpenAPI: `/docs` di tiap service FastAPI
