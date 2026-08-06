# Changelog

Semua perubahan penting Open Radiology Platform. Format: [Keep a Changelog](https://keepachangelog.com/).
Versi: [SemVer](https://semver.org).

## [v0.1.0] - 2026-08-06

Milestone MS0–MS13: foundation monorepo hingga integrasi DICOM-native penuh.

### Ditambahkan

**Foundation (MS0)**
- Monorepo: `products/` (bounded context), `packages/`, `platform/`, `scripts/`, `docs/`, `legacy/`
- 5 ADR (arsitektur, composability, Orthanc sebagai satu-satunya penyimpan DICOM, backup, compose)
- Platform: Orthanc + PostgreSQL + OHIF viewer (compose, `data/orthanc`)
- `scripts/`: check (5/5), health, backup (storage+index), restore (disabled-by-default)
- 6 produk bootable: OMC API, AI, Integration, RIS (Laravel+DDEV), OMC Console (SvelteKit), Developer Portal (Vitepress)

### MS1–MS13

- **dicom-core** (`packages/dicom-core`): `parse()` metadata, `preview()` pixel→PNG (window/level, MONOCHROME1), `echo()`/`store()` C-ECHO/C-STORE ke Orthanc
- **OMC API**: vertical slice import→queue→preview→store; CORS; console dashboard/queue/viewer (Svelte 5)
- **RIS API**: patients, orders (auto worklist item), worklist (MWL source), reports (findings/impression/status); `PATCH /orders/{id}/status`
- **FHIR R4**: Patient (search identifier/name), ServiceRequest, DiagnosticReport, Bundle searchset (Content-Type `application/fhir+json`)
- **Integration service**: MORBIS (SEP + klaim, HMAC signature, mock/real), HL7 v2 (ADT-A01→RIS patient, ORM-O01), MWL SCP (C-FIND :4243), MPPS SCP (N-CREATE/N-SET :4244 → status order)
- **AI Service**: analisis statistik dari Orthanc (VOI LUT → mean/std/percentile + finding; `engine: statistik-v1`)
- **Security**: API key auth (`X-API-Key`) untuk endpoint eksternal integration; kredensial hanya via `.env` (SECURITY.md)
- **Developer portal**: 8 halaman docs per produk (API, integrasi, contoh)

### Test

- 84 passed total: RIS 51 (143 assertions) · Integration 18 · OMC 6 · AI 5 · dicom-core 4
- Test safety: sqlite :memory: untuk RIS (tidak menyentuh DB dev); teardown MPPS/MWL tidak mencemari Orthanc

### Catatan

- Antrean OMC in-memory (hilang saat restart) — SQLite/persisten di versi berikut
- AI engine statistik-v1 — model ML di milestone berikutnya
- MORBIS mock default — `MORBIS_MODE=real` + kredensial BPJS untuk integrasi sandbox/produksi