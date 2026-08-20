# PRD: ORP RIS (Radiology Information System)

## Introduction

Sistem administrasi radiologi. Mengelola seluruh workflow non-gambar: pasien, dokter, departemen, order, prosedur, laporan, verifikasi, dan audit. RIS adalah sumber kebenaran untuk data administratif; gambar ditangani PACS (Orthanc), viewer oleh Viewer (OHIF).

Basis: roadmap MS1–13 sudah selesai (release v0.1.0). Dokumen ini memetakan ulang capaian ke fase produk dan mendefinisikan fase berikutnya.

## Goals

- Satu sumber kebenaran untuk pasien, order, dan laporan radiologi
- Workflow order → pemeriksaan → laporan → verifikasi terlacak penuh
- Integrasi dengan OMC (worklist), PACS, Integration Platform, AI
- Dashboard operasional real-time untuk petugas, radiografer, radiolog
- **Developer Portal**: Integrasi dokumentasi API, sandbox test, dan livedemo fitur RIS

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- API pasien (CRUD), order (CRUD + status PATCH), worklist (sumber MWL), reports (findings/impression/status) — MS3, MS5
- Order auto-create WorklistItem — MS3
- Dashboard UI (statistik, order terbaru, worklist) — sesi aktif (belum di-commit)
- Auth + verified middleware aktif — sesi aktif
- Test 51 (143 assertions)

Belum tercover: scheduling, notification, template report, signature, role/permission, audit trail, **Developer Portal**, HL7/FHIR keluar (datang lewat Integration).

## Fase

- **MVP (v0.2):** Login, Patient, Doctor, Order, Procedure, Report, Dashboard
- **V2 (v0.6):** Scheduling, Notification, Template Report, Signature, Role, Audit
- **V3 (v0.7):** **Developer Portal Integration** + HL7/FHIR basics
- **V4:** HL7, FHIR, Analytics, Multi Hospital, Billing

## User Stories

### US-RIS-001: Halaman Dashboard operasional

**Description:** Sebagai petugas radiologi, saya ingin dashboard menampilkan statistik dan order aktif agar bisa memantau beban kerja.

**Acceptance Criteria:**
- [ ] Statistik: total pasien, order aktif (scheduled+in_progress), worklist pending, laporan final
- [ ] Tabel 5 order terbaru dengan status badge dan tombol ke detail
- [ ] Panel worklist pending + scheduled AET
- [ ] Auth + verified middleware aktif
- [ ] `ddev artisan test` lulus (min. 51 test)
- [ ] Verify in browser using dev-browser skill

### US-RIS-002: CRUD Doctor

**Description:** Sebagai petugas, saya ingin mengelola data dokter pengirim agar order tercatat referrer yang benar.

**Acceptance Criteria:**
- [ ] Model + migration `doctors` (name, specialization, phone, NIP/NIDN)
- [ ] Halaman index/create/edit/delete
- [ ] Relasi `Order belongsTo Doctor` (nullable)
- [ ] Test CRUD lulus

### US-RIS-003: CRUD Procedure

**Description:** Sebagai petugas, saya ingin mengelola master prosedur (kode, nama, tarif) agar order memakai prosedur baku.

**Acceptance Criteria:**
- [ ] Model + migration `procedures` (code, name, body_part, modality)
- [ ] Halaman CRUD
- [ ] Relasi `Order belongsTo Procedure`
- [ ] Test CRUD lulus

### US-RIS-004: Halaman Worklist operasional

**Description:** Sebagai radiografer, saya ingin melihat worklist item per status agar tahu pasien yang sudah datang/mulai.

**Acceptance Criteria:**
- [ ] Filter status (pending/arrived/started/completed)
- [ ] Update status item (arrived → started → completed)
- [ ] Tampil scheduled AET + waktu
- [ ] Verify in browser using dev-browser skill

### US-RIS-005: Halaman Report dengan template

**Description:** Sebagai radiolog, saya ingin menulis laporan dengan template agar konsisten dan cepat.

**Acceptance Criteria:**
- [x] Halaman report per order (findings + impression)
- [x] Simpan draft, ubah status (draft → final)
- [x] Template report (isi default dari prosedur) — V2 (v0.6)
- [x] Tanda tangan radiolog (signed_by, signed_at) saat finalisasi — V2 (v0.6)
- [ ] Verify in browser using dev-browser skill

### US-DEV-001: Halaman Developer Portal

**Description:** Sebagai developer, saya ingin mengakses dokumentasi API RIS lengkap untuk integrasi sistem.

**Acceptance Criteria:**
- [x] Halaman utama portal dengan navigasi fitur
- [x] Dokumentasi endpoint API (Patient, Order, Report)
- [x] Sandbox mode untuk testing tanpa data real
- [x] Contoh request/response JSON
- [x] Tombol "Copy to Clipboard" untuk request curl
- [x] Verify in browser using dev-browser skill

### US-DEV-002: Livewire Components Integration

**Description:** Sebagai developer, saya ingin menggunakan Livewire components untuk interaksi dinamis dengan RIS.

**Acceptance Criteria:**
- [x] Livewire component untuk fetch worklist otomatis
- [x] Livewire component untuk membuat order baru
- [x] Livewire component untuk status update laporan
- [x] Real-time update tanpa refresh halaman
- [x] Verify in browser using dev-browser skill

### US-DEV-003: API Authentication Demo

**Description:** Sebagai developer, saya ingin melihat cara otentikasi API key yang digunakan RIS.

**Acceptance Criteria:**
- [x] Menampilkan format header `X-API-Key`
- [x] Demo mode (API key kosong = dev) dan mode production
- [x] Contoh request dengan dan tanpa key
- [x] Error response saat key salah/dibatalkan
- [x] Verify in browser using dev-browser skill

## Functional Requirements

- FR-1: CRUD Patient (identifier unik, name, gender, birth_date, phone)
- FR-2: CRUD Order dengan status: scheduled → in_progress → completed / cancelled
- FR-3: Order auto-create WorklistItem saat dibuat
- FR-4: WorklistItem status: pending → arrived → started → completed
- FR-5: Report per order: findings, impression, status (draft/final)
- FR-6: Dashboard: aggregate pasien/order/worklist/report + 5 order terbaru
- FR-7: Semua halaman membutuhkan auth + email verified
- FR-8: Dashboard & halaman CRUD memakai layout bersama (main/app) dan font Outfit + JetBrains Mono
- **FR-9: Developer Portal menampilkan dokumentasi API dan Livewire components**
- **FR-10: Livewire components untuk interaksi dinamis (worklist, order, report)**

## Non-Goals

- Tidak menyimpan gambar DICOM (PACS bertanggung jawab)
- Tidak ada viewer gambar (Viewer/OHIF)
- Tidak ada billing di MVP
- Tidak ada HL7/FHIR langsung dari RIS (lewat Integration Platform)
- Tidak ada multi-tenant di MVP

## Design Considerations

- Tailwind v4 (`@import "tailwindcss"` + `@custom-variant dark`)
- Font: Outfit (sans) + JetBrains Mono (angka) via Google Fonts
- Layout: `components/layouts/main.blade.php` + `app.blade.php` + `x-ui.app.header`
- Dashboard: grid 4 kolom statistik, gap-px border, angka font-mono, badge status, empty state
- Tema warna: emerald accent
- Emoji banned
- **Livewire components digunakan di halaman dashboard dan portal**
- **Semua `@vite` assets digunakan untuk JS/Livewire bundles**

## Technical Considerations

- Laravel 12 + Volt + Folio + Livewire (route otomatis dari `resources/views/pages/`)
- DB: MySQL dev (DDEV `db/db/db`), test sqlite `:memory:` (RefreshDatabase)
- Auth: Laravel starter kit (Folio auth pages di `/auth/login`)
- Relasi: Patient hasMany Order, Order belongsTo Patient/Doctor/Procedure, Order hasOne WorklistItem, Order hasOne Report
- Integrasi keluar: worklist/MWL & MPPS dikelola Integration Platform (bukan RIS langsung)
- **Livewire components** `@livewire('component-name')` digunakan di blade templates
- **State management** via `$event`, `$wire`, `@livewire('component-name')`
- **Loading states** dengan `@if($isLoading)` dan `@error($field)`
- **Conditional rendering** dengan `$page` dan `@unless`

## Success Metrics

- 100% flow order teruji (test 51 → target 60+ setelah modul baru)
- Waktu buat order < 60 detik (uji manual)
- Dashboard render < 1 detik dengan data dev
- **Livewire components bekerja tanpa error** di browser

## Open Questions

- Dokter & prosedur diimpor dari SIMRS (MORBIS) atau diinput manual?
- Status order vs status worklist: siapa pemilik kebenaran (RIS atau MPPS)?
- **Developer Portal**: fitur mana yang harus ada di MVP vs faseanjut?
- **Livewire**: komponen mana yang harus dibuat pertama (worklist fetch, order create, report update)?
- Migration MySQL → PostgreSQL untuk produksi (V2)?

## Developer Platform (Produk 7) — Tambahan

### Goals

- Dokumentasi API lengkap semua layanan (OpenAPI)
- Tutorial & contoh integrasi per produk
- OpenAPI + Postman collection
- CLI untuk bootstrap & utilitas DICOM
- Template adapter untuk SIMRS baru

### Status Saat Ini

Sudah tercover:
- Developer portal (Vitepress) `products/developer-portal` — 8 halaman docs: API, integrasi, contoh per produk — MS11
- ADR (docs/adr/ 1–5)

Belum tercover: OpenAPI/Postman collection otomatis, plugin SDK, CLI, template adapter.

### Fase

- **MVP (v0.9):** Developer Portal, OpenAPI & Postman Collection
- **V2 (v1.0):** Plugin SDK, Template Adapter
- **V3:** Full Plugin ecosystem

### User Stories

#### US-DEV-001: OpenAPI & Postman Collection per layanan

**Description:** Sebagai developer, saya ingin spesifikasi OpenAPI + Postman untuk tiap API (RIS, OMC, integration, AI) agar bisa integrasi cepat.

**Acceptance Criteria:**
- [ ] OpenAPI spec (JSON/YAML) di-generate dari FastAPI (`/openapi.json`) & Laravel (dokumentasi)
- [ ] Postman collection exportable per produk
- [ ] Tautan dari portal ke collection
- [ ] Verify in browser using dev-browser skill

#### US-DEV-002: Template adapter SIMRS (V2)

**Description:** Sebagai integrator, saya ingin template adapter untuk SIMRS baru agar integrasi dimulai cepat.

**Acceptance Criteria:**
- [ ] Folder contoh adapter (gaya MORBIS/OpenMRS) dengan stub kosong
- [ ] Panduan langkah: folder → kredensial → daftarkan adapter
- [ ] Test stub pass
- [ ] Contoh di portal

#### US-DEV-003: CLI utilities DICOM (V3)

**Description:** Sebagai programmer, saya ingin CLI untuk task DICOM umum (anonymize, inspect, convert) agar cepat prototyping.

**Acceptance Criteria:**
- [ ] Perintah `orp-cli dcm inspect <file>`, `orp-cli dcm anonymize <file>`, `orp-cli dcm png <file>`
- [ ] Test CLI lulus

### Functional Requirements

- FR-1: Portal menampilkan API docs autentik per produk
- FR-2: Link langsung ke spec (OpenAPI/Postman) dari tiap halaman API
- FR-3: Contoh kode integrasi per produk (Java/Curl)
- FR-4: Template Adapter (V2) new kasus
- FR-5: CLI bantuan utama (V3)

### Non-Goals

- Tidak ada UI runtime ORP di portal (hanya docs)
- Tidak ada starter/reference code-blob besar di MVP
- Tidak ada akses healthcare data produksi

### Design Considerations

- Portal: Vitepress `products/developer-portal` (sudah ada) — perdalam, jangan rewrite
- Konsisten dengan struktur API RIS/OMC

### Technical Considerations

- OpenAPI: FastAPI auto-generated (`/openapi.json`); Laravel butuh dokumentasi manual/scaffold
- CLI: Python (click/typer) untuk DICOM tools — reuse `packages/dicom-core`
- Postman: generate dari OpenAPI

### Success Metrics

- Portal 8 halaman → target 20+
- Semua collection bisa diimport clear sekali dev
- New SIMRS adapter (template) < 2 hari kerja

### Open Questions

- Fokus template: SIMRS lokal (MORBIS) atau SATUSEHAT/FHIR?
- CLI validasi DICOM (dcmtk) atau cukup pynetdicom/dicom_core?
- Postman collection: perlu autosync CI?

---

**Catatan**: Dokumen ini menggabungkan PRD RIS (Radiology Information System) dan PRD Developer Platform (Produk 7). Bagian RIS fokus pada workflow administrasi radiologi, sedangkan bagian Developer Platform fokus pada dokumentasi, CLI, dan integrasi bagi developer dan integrator sistem.