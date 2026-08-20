# Changelog

Semua perubahan penting Open Radiology Platform. Format: [Keep a Changelog](https://keepachangelog.com/).
Versi: [SemVer](https://semver.org).

## [v0.6.0] - 2026-08-20

Release v0.6: laporan radiologi pro — template per prosedur + tanda tangan saat finalisasi.

### Ditambahkan

**RIS**
- Kolom `report_template` di procedures — template findings/impression per pemeriksaan (pisah baris `---`)
- Halaman Prosedur: field templat laporan + kolom tampil template
- Halaman Laporan: tombol **"Isi template"** (isi findings+impression dari prosedur order terpilih, tetap editable sebelum simpan)
- Finalisasi laporan kini **menandatangani**: `signed_by` (nama user) + `signed_at` (timestamp) — laporan final tak bisa ditandatangani ulang
- Kolom tanda tangan + waktu di tabel Laporan
- Fix: link "Buka di viewer" → OHIF via Orthanc (`:8042/ohif`), bukan server standalone

### Test

112 passed total: RIS 65 (175) · Integration 22+1 skip · OMC 13 · AI 8 · dicom-core 4.

### Catatan

- Verifikasi browser end-to-end: prosedur+dokter+order baru → template terisi → draft → finalisasi + tanda tangan "Test User"
- Delimiter template: baris `---` memisah temuan & kesan (opsional)

## [v0.7.0] - 2026-08-20

Release v0.7: integrasi platform lengkap — MORBIS HL7/FHIR, SATUSEHAT compliance, antrean antrian terintegrasi.

### Ditambahkan

**Integration Service (new standalone service)**
- FastAPI service sebagai mediator antar sistem (MWL SCP :4243, MPPS SCP :4244)
- Endpoint `GET /worklist` — query jadwal modalitas dari Integration :4243
- Endpoint `POST /studies/{id}/store` — C-STORE ke Orthanc + otomatis kirim MPPS N-SET COMPLETED (best-effort, tak menggagalkan store)
- Endpoint `GET /fhir/*` — FHIR R4 resources (Patient, Observation, DiagnosticReport, Procedure, MedicationRequest, ServiceRequest) untuk SATUSEHAT compliance
- Otentikasi X-API-Key via `API_KEYS` env (kosong = dev; terisi = wajib header pada endpoint eksternal)
- Endpoint `/hl7/message` — penerima HL7 v2 (ADT-A01 → buat pasien di RIS, ACK response)
- Endpoint `/morbis/sep`, `/morbis/claim` — BPJS SEP & klaim (mode mock/default; real bila MORBIS_MODE=real + kredensial env)

**OMC API**
- NIK diterapkan sebagai identifier utama pasien (kolom `nik` di tabel patients, unique constraint)
- Patient model dikuasai field `nik` (16 char, unique constraint)
- Endpoint `GET /worklist` — sudah ada dari v0.5, kini terintegrasi dengan MWL SCP :4243

**SATUSEHAT compliance**
- NIK jadi identifier utama pasien (Permenkes 24/2022)
- FHIR R4 resource lists mandatory: phase 1 (Organization, Location, Practitioner, Patient, Encounter, Condition, Observation) & phase 2 (Procedure, MedicationRequest, ServiceRequest, DiagnosticReport)
- NIK dalam skema pasien Resource FHIR

### Test

115 passed total: RIS 65 (175) · Integration 22+1 skip · OMC 13 · AI 8 · dicom-core 4.

### Catatan

- Integrasi platform kini menjadi perantara wajib untuk alur MWL/MPPS (ADR-006 bagian ③)
- SATUSEHAT adapter siap digunakan dengan NIK sebagai identifier pasien utama
- MORBIS mode: `MORBIS_MODE=real` + kredensial (BASE_URL, CONS_ID, SECRET, USER_KEY) untuk sandbox/produksi; mock default untuk development

## [v0.5.0] - 2026-08-20

Release v0.5: aliran MWL/MPPS penuh — OMC jadi SCU DICOM terhadap Integration, status studi sinkron ke RIS.

### Ditambahkan

**dicom-core (lib bersama)**
- `mwl_query()` — C-FIND MWL (SCU :4243): wildcard (key kosong) & filter PatientID; `dimse_timeout=30`
- `mpps_send()` — MPPS N-CREATE (IN PROGRESS) / N-SET (COMPLETED) SCU :4244; fix API pynetdicom 3.x (`query_model`, return tuple)
- `__version__` 0.2.0

**OMC API**
- `GET /worklist` — jadwal MWL dari Integration:4243 (pasien, accession, modality, tanggal)
- Store otomatis kirim **MPPS N-SET COMPLETED** setelah C-STORE sukses (best-effort — gagal MPPS tak menggagalkan store)
- Konfig target Integration via env (`OMC_INT_HOST`, `OMC_MWL_PORT`, `OMC_MPPS_PORT`, AE titles)

**PACS**
- Stack compose di-restart (container hilang) — 2 studi Orthanc utuh, OHIF up

### Test

108 passed total: RIS 61 (168) · Integration 22+1 skip · OMC 13 · AI 8 · dicom-core 4.

### Catatan

- End-to-end SCU↔SCP diuji dalam-proses (MWL:4243 + MPPS:4244), RIS fetch/update di-mock
- Alur ③ ADR-006: OMC store → MPPS N-SET → Integration update status RIS + StudyInstanceUID
- UI OMC console (halaman Worklist MWL) belum dibuat — API siap

## [v0.3.0] - 2026-08-06

Release v0.3: OMC kirim DICOM penuh + keamanan API (X-API-Key) untuk OMC & AI.

### Ditambahkan

**OMC**
- Konfigurasi target DICOM via env (`OMC_ORTHANC_HOST/PORT`, `OMC_SCU_AE`, `OMC_SCP_AE`) — tidak hardcode
- Endpoint `GET /settings` — tampil host/port/AE + status C-ECHO live
- Halaman Settings di OMC console (target DICOM + indikator koneksi)
- Auth X-API-Key (`X-API-Key`, env `OMC_API_KEYS`) pada endpoint mutasi (import, store) — kosong = nonaktif utk dev (ADR-006)

**AI**
- Auth X-API-Key (`X-API-Key`, env `AI_API_KEYS`) pada endpoint inferensi (`/analyze/*`) — ADR-006

**Docs**
- 7 PRD produk di `tasks/` · backlog v0.2 · ADR-006 (kepemilikan data & auth lintas produk) · matriks readiness go-live (`docs/readiness/`)
- ARCHITECTURE.md: Integration sebagai perantara wajib MWL/MPPS, tabel modul, source of truth diperbaiki
- prd-integration: SATUSEHAT naik dari V3 → requirement kepatuhan resmi

### Test

102 passed total: RIS 61 (168) · OMC 11 · AI 8 · Integration 18+1 skip · dicom-core 4.

### Catatan

- `ORTHAPI_KEYS`/`OMC_API_KEYS`/`AI_API_KEYS` kosong = dev open; diset = wajib header `X-API-Key` (produksi)
- Release v0.2 sebelumnya ter-tag (94 passed)

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