# PRD: ORP Developer Platform (Produk 7)

## Introduction

Produk untuk developer & integrator, bukan pengguna rumah sakit. Pembedaan ORP dengan PACS open-source lain. Sasaran: mendokumentasikan, memperluas, dan mengintegrasikan ORP oleh komunitas.

## Goals

- Dokumentasi API lengkap semua layanan (OpenAPI)
- Tutorial & contoh integrasi per produk
- OpenAPI + Postman collection
- CLI untuk bootstrap & utilitas DICOM
- Template adapter untuk SIMRS baru

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- Developer portal (Vitepress) `products/developer-portal` — 8 halaman docs: API, integrasi, contoh per produk — MS11
- OpenAPI: FastAPI `/openapi.json` + Laravel Scramble `/docs/api` + `docs/api.json` (auto-generated)
- Postman: `docs/api/postman/{ris,omc,ai,integration}.postman-collection.json` via `scripts/gen-postman.py`
- ADR (docs/adr/ 1–5)

Belum tercover: plugin SDK, CLI, template adapter.

## Fase

- **MVP (v0.9):** Developer Portal, OpenAPI & Postman Collection
- **V2 (v1.0):** Plugin SDK, Template Adapter
- **V3:** Full Plugin ecosystem

## User Stories

### US-DEV-001: OpenAPI & Postman Collection per layanan
**Description:** Sebagai developer, saya ingin spesifikasi OpenAPI + Postman untuk tiap API (RIS, OMC, integration, AI) agar bisa integrasi cepat.

**Acceptance Criteria:**
- [x] OpenAPI spec (JSON/YAML) di-generate dari FastAPI (`/openapi.json`) & Laravel (Scramble `docs/api.json`, 12 endpoint) — FastAPI auto `/openapi.json`; Laravel via `dedoc/scramble`.
- [x] Postman collection exportable per produk — `docs/api/postman/{omc,ai,integration,ris}.postman-collection.json` (via `scripts/gen-postman.py`, stdlib only).
- [x] Tautan dari portal ke collection — portal `/products/omc` + `docs/index.md`, RIS developer portal tautan Scramble `/docs/api`.
- [ ] Verify in browser using dev-browser skill

### US-DEV-002: Template adapter SIMRS (V2)
**Description:** Sebagai integrator, saya ingin template adapter untuk SIMRS baru agar integrasi dimulai cepat.

**Acceptance Criteria:**
- [ ] Folder contoh adapter (gaya MORBIS/OpenMRS) dengan stub kosong
- [ ] Panduan langkah: folder → kredensial → daftarkan adapter
- [ ] Test stub pass
- [ ] Contoh di portal

### US-DEV-003: CLI utilities DICOM (V3)
**Description:** Sebagai programmer, saya ingin CLI untuk task DICOM umum (anonymize, inspect, convert) agar cepat prototyping.

**Acceptance Criteria:**
- [ ] Perintah `orp-cli dcm inspect <file>`, `orp-cli dcm anonymize <file>`, `orp-cli dcm png <file>`
- [ ] Test CLI lulus

## Functional Requirements

- FR-1: Portal menampilkan API docs autentik per produk
- FR-2: Link langsung ke spec (OpenAPI/Postman) dari tiap halaman API
- FR-3: Contoh kode integrasi per produk (Java/Curl)
- FR-4: Template Adapter (V2) new kasus
- FR-5: CLI bantuan utama (V3)

## Non-Goals

- Tidak ada UI runtime ORP di portal (hanya docs)
- Tidak ada starter/reference code-blob besar di MVP
- Tidak ada akses healthcare data produksi

## Design Considerations

- Portal: Vitepress `products/developer-portal` (sudah ada) — perdalam, jangan rewrite
- Konsisten dengan struktur API RIS/OMC

## Technical Considerations

- OpenAPI: FastAPI auto-generated (`/openapi.json`); Laravel butuh dokumentasi manual/scaffold
- CLI: Python (click/typer) untuk DICOM tools — reuse `packages/dicom-core`
- Postman: generate dari OpenAPI

## Success Metrics

- Portal 8 halaman → target 20+
- Semua collection bisa diimport clear sekali dev
- New SIMRS adapter (template) < 2 hari kerja

## Open Questions

- Fokus template: SIMRS lokal (MORBIS) atau SATUSEHAT/FHIR?
- CLI validasi DICOM (dcmtk) atau cukup pynetdicom/dicom_core?
- Postman collection: perlu autosync CI?