# PRD: ORP RIS (Radiology Information System)

## Introduction

Sistem administrasi radiologi. Mengelola seluruh workflow non-gambar: pasien, dokter, departemen, order, prosedur, laporan, verifikasi, dan audit. RIS adalah sumber kebenaran untuk data administratif; gambar ditangani PACS (Orthanc), viewer oleh Viewer (OHIF).

Basis: roadmap MS1–13 sudah selesai (release v0.1.0). Dokumen ini memetakan ulang capaian ke fase produk dan mendefinisikan fase berikutnya.

## Goals

- Satu sumber kebenaran untuk pasien, order, dan laporan radiologi
- Workflow order → pemeriksaan → laporan → verifikasi terlacak penuh
- Integrasi dengan OMC (worklist), PACS, Integration Platform, AI
- Dashboard operasional real-time untuk petugas, radiografer, radiolog

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- API pasien (CRUD), order (CRUD + status PATCH), worklist (sumber MWL), reports (findings/impression/status) — MS3, MS5
- Order auto-create worklist item — MS3
- Dashboard UI (statistik, order terbaru, worklist) — sesi aktif (belum di-commit)
- Auth + verified middleware aktif — sesi aktif
- Test 51 (143 assertions)

Belum tercover: scheduling, notification, template report, signature, role/permission, audit trail, HL7/FHIR keluar (datang lewat Integration).

## Fase

- **MVP (v0.2):** Login, Patient, Doctor, Order, Procedure, Report, Dashboard
- **V2 (v0.6):** Scheduling, Notification, Template Report, Signature, Role, Audit
- **V3:** HL7, FHIR, Analytics, Multi Hospital, Billing (HL7/FHIR lewat Integration Platform)

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
- [ ] Halaman report per order (findings + impression)
- [ ] Simpan draft, ubah status (draft → final)
- [ ] Template report (isi default dari prosedur) — V2
- [ ] Verify in browser using dev-browser skill

## Functional Requirements

- FR-1: CRUD Patient (identifier unik, name, gender, birth_date, phone)
- FR-2: CRUD Order dengan status: scheduled → in_progress → completed / cancelled
- FR-3: Order auto-create WorklistItem saat dibuat
- FR-4: WorklistItem status: pending → arrived → started → completed
- FR-5: Report per order: findings, impression, status (draft/final)
- FR-6: Dashboard: aggregate pasien/order/worklist/report + 5 order terbaru
- FR-7: Semua halaman membutuhkan auth + email verified
- FR-8: Dashboard & halaman CRUD memakai layout bersama (main/app) dan font Outfit + JetBrains Mono

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

## Technical Considerations

- Laravel 12 + Volt + Folio + Livewire (route otomatis dari `resources/views/pages/`)
- DB: MySQL dev (DDEV `db/db/db`), test sqlite `:memory:` (RefreshDatabase)
- Auth: Laravel starter kit (Folio auth pages di `/auth/login`)
- Relasi: Patient hasMany Order, Order belongsTo Patient/Doctor/Procedure, Order hasOne WorklistItem, Order hasOne Report
- Integrasi keluar: worklist/MWL & MPPS dikelola Integration Platform (bukan RIS langsung)

## Success Metrics

- 100% flow order teruji (test 51 → target 60+ setelah modul baru)
- Waktu buat order < 60 detik (uji manual)
- Dashboard render < 1 detik dengan data dev

## Open Questions

- Dokter & prosedur diimpor dari SIMRS (MORBIS) atau diinput manual?
- Status order vs status worklist: siapa pemilik kebenaran (RIS atau MPPS)?
- Migration MySQL → PostgreSQL untuk produksi (V2)?
