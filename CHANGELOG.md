# Changelog

Semua perubahan penting Open Radiology Platform. Format: [Keep a Changelog](https://keepachangelog.com/).
Versi: [SemVer](https://semver.org).

## [v0.2.0] - 2026-08-06

Release v0.2: RIS + PACS berjalan — workflow administrasi radiologi penuh di UI, backup otomatis, jembatan studi ke viewer.

### Ditambahkan

**RIS UI (Folio/Volt)**
- Dashboard operasional (statistik, order terbaru, worklist) + auth/verified
- CRUD Doctor (referrer) & master Procedure (kode, nama, bagian tubuh, modality)
- Order baru: pilih pasien/dokter/prosedur, modality auto-isi dari prosedur, auto-create slot worklist
- Halaman Worklist operasional (filter status, update pending→arrived→started→completed)
- Halaman Laporan (findings/impression, draft→final)
- Nav bar RIS: Dashboard · Order · Worklist · Laporan · Dokter · Prosedur
- Tombol "Buka di viewer →" (OHIF) saat order punya StudyInstanceUID

**RIS API**
- `PATCH /orders/{id}/status` terima `study_instance_uid`
- Kolom `study_instance_uid` di orders (jembatan RIS→viewer)

**Integration**
- MPPS N-SET membawa StudyInstanceUID → set status + study_uid order sekaligus
- Fix: hapus paket `app` stale di venv (shadow) + `pythonpath` pytest — 18 test hijau

**PACS**
- Backup harian otomatis (systemd user timer `orp-backup`, daily 00:00, persistent)
- Restore teruji (2 studi utuh setelah restore)

**AI**
- Fix HTTPError series (404) — endpoint `/analyze/series` tangani missing series
- Fix: hapus shadow paket `app` stale — 5 test hijau

### Test

94 passed total: RIS 61 (168 assertions) · Integration 18+1 skip · OMC 6 · AI 5 · dicom-core 4.

### Catatan

- Backup systemd: `systemctl --user status orp-backup.timer` (user timers)
- Restore tetap disabled-by-default: `scripts/restore.sh --run <storage> <sql>`
- 7 PRD produk + backlog v0.2 di `tasks/` (prd-ris/omc/pacs/viewer/integration/ai/developer-platform)

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