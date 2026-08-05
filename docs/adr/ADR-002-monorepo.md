# ADR-002 — Monorepo Terstruktur per Produk (bounded context)

- Status: **Accepted** (2026-08-05)
- Konteks: satu platform dengan banyak aplikasi (RIS, OMC, AI, viewer,
  integrasi). Perlu berbagi package tapi memisahkan domain bisnis.

## Keputusan

Monorepo tunggal, terstruktur berdasarkan **produk (bounded context)**, bukan
framework:

```
products/           # produk = domain bisnis
├── ris/            # Hospital Workflow (Laravel + DDEV)
├── omc/            # Modality Workflow (FastAPI + Svelte)
│   ├── api/
│   └── console/
├── ai/             # Inference & CAD (FastAPI)
├── viewer/         # OHIF integration
└── integration/    # Adapter SIMRS/FHIR/HL7
packages/           # library reusable, tidak boleh depend ke products/
platform/           # infrastruktur (orthanc, postgres, redis, ohif, gateway, monitoring)
```

### Aturan dependency

- ✅ `products/*` boleh menggunakan `packages/*`
- ❌ `packages/*` **tidak boleh** bergantung pada `products/*`
- `platform/*` berdiri sendiri (docker services)

## Alasan

- RIS dan OMC domain berbeda → berkembang sendiri (produk bisa dipisah jadi
  repo terpisah di masa depan tanpa ubah struktur berpikir).
- Satu history git, satu dokumentasi, cocok untuk tim kecil (1–5 dev).
- Struktur berbasis produk tahan pergantian teknologi (RIS bisa Laravel →
  .NET/Go tanpa ubah struktur).

## Konsekuensi

- Repo besar; CI perlu path-scoped.
- RIS dijalankan via DDEV; produk lain via Docker Compose (ADR-005).

## Lihat juga

- ADR-001 (arsitektur), ADR-005 (compose strategy)
