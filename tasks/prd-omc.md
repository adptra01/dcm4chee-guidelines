# PRD: ORP OMC (Open Modality Console)

## Introduction

Workstation radiografer — pengganti PZDR. Menghubungkan modalitas ke PACS: import DICOM, preview, QC, queue, transmisi ke Orthanc. OMC tidak menangani administrasi (RIS) dan tidak melakukan analisis AI (AI Platform).

Basis: MS1–13 selesai (v0.1.0). Dokumen ini memetakan ulang capaian dan mendefinisikan fase berikutnya.

## Goals

- Import studi DICOM (file/disk/USB) dengan metadata lengkap
- Preview gambar (window/level) sebelum transmisi
- Antrean transmisi ke Orthanc yang persisten dan bisa retry
- Status transmisi terlacak (dikirim/gagal/diulang)
- Konfigurasi AET & tujuan per modalitas

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- `packages/dicom-core`: parse metadata, preview pixel→PNG (window/level, MONOCHROME1), echo/store ke Orthanc — MS1
- OMC API vertical slice: import → queue → preview → store; CORS; queue SQLite persisten (`app/queue_store.py`) — MS2, MS13 fix
- Console (SvelteKit): dashboard, queue, viewer dasar — MS4
- Test 6 (OMC) + 4 (dicom-core)

Belum tercover: MWL (datang dari Integration), MPPS (dikirim ke Integration), retry UI, settings, deteksi modalitas, auto-routing.

## Fase

- **MVP (v0.3):** Import DICOM, Preview, Queue, Send, Retry, Settings
- **V2 (v0.5+):** MWL, MPPS, Detector SDK, Image Processing, Overlay
- **V3:** Auto Routing, Protocol Matching, Dose, Auto QA

## User Stories

### US-OMC-001: Import file DICOM
**Description:** Sebagai radiografer, saya ingin mengimpor file/folder DICOM agar studi masuk ke antrean.

**Acceptance Criteria:**
- [ ] Upload single file & folder (multi-file) via UI
- [ ] Parse metadata DICOM (parse() dari dicom-core)
- [ ] Validasi file non-DICOM ditolak dengan pesan jelas
- [ ] Item masuk queue SQLite persisten
- [ ] Test importer lulus

### US-OMC-002: Preview gambar
**Description:** Sebagai radiografer, saya ingin melihat preview sebelum kirim agar tahu kualitas gambar.

**Acceptance Criteria:**
- [ ] Preview PNG dari pixel data (window/level, MONOCHROME1)
- [ ] Tampil di halaman detail studi
- [ ] Test preview lulus

### US-OMC-003: Transmisi ke Orthanc + retry
**Description:** Sebagai radiografer, saya ingin mengirim studi ke PACS dan bisa mengulang yang gagal agar tidak ada studi hilang.

**Acceptance Criteria:**
- [ ] C-STORE ke Orthanc (store() dari dicom-core, host default localhost)
- [ ] Status per item: pending → sending → sent / failed
- [ ] Tombol retry untuk item failed
- [ ] Queue bertahan setelah restart (SQLite)
- [ ] Test store + retry lulus

### US-OMC-004: Settings tujuan
**Description:** Sebagai radiografer, saya ingin mengonfigurasi AET & host Orthanc agar bisa menunjuk PACS berbeda.

**Acceptance Criteria:**
- [ ] Form AET source, AET target, host, port (default 4242)
- [ ] Tersimpan (env/file config)
- [ ] Test config lulus

## Functional Requirements

- FR-1: Import DICOM single + folder, validasi, parse metadata
- FR-2: Preview PNG dengan window/level & dukungan MONOCHROME1
- FR-3: Queue SQLite persisten (connect-per-op, lock) — sudah ada, jangan regresi
- FR-4: C-STORE ke Orthanc; host default localhost
- FR-5: Status per item + retry failed
- FR-6: C-ECHO (echo()) untuk uji koneksi dari Settings
- FR-7: Config AET/host/port per tujuan

## Non-Goals

- Tidak ada administrasi pasien/order (RIS)
- Tidak ada AI/analisis di OMC (AI Platform)
- Tidak ada viewer penuh (Viewer/OHIF)
- Tidak ada MWL/MPPS di MVP (Integration Platform; V2)

## Design Considerations

- UI: SvelteKit + Svelte 5 (`$state` runes) — konsisten dengan console yang ada
- CORS enabled untuk origin console
- Tema: konsisten dengan RIS (emerald accent, font-mono angka) jika memungkinkan

## Technical Considerations

- FastAPI backend; pydicom; pynetdicom
- `packages/dicom-core` — dipakai, bukan di-copy
- Queue: SQLite `data/queue.db` via `queue_store.py` (jangan kembali ke dict in-memory)
- Ports: Orthanc DICOM :4242, REST :8042; MWL :4243 & MPPS :4244 dikelola Integration (V2)

## Success Metrics

- Import→sent flow teruji otomatis (test OMC 6 → target 12+)
- Tidak ada studi hilang saat restart (queue persisten)
- Retry sukses tanpa studi duplikat di Orthanc (diff set instance)

## Open Questions

- Deteksi modalitas otomatis dari metadata (V3)?
- Auto-routing berdasarkan modality/modality worklist (V3)?
- OMC console sekarang memakai bahasa apa — perlu i18n?
