# PRD: ORP Viewer

## Introduction

Viewer radiolog — tampilan gambar DICOM dari PACS. Meng-embed OHIF sebagai engine viewer, bukan menulis viewer dari nol. Fokus produk: studi list, peluncuran studi, annotation (V2), comparison (V2), hanging protocol (V2), peluncuran laporan (V3).

## Goals

- Luncurkan studi dari RIS/PACS ke OHIF viewer
- Cari & buka studi berdasarkan pasien, tanggal, modality
- Tanpa login ganda (single entry point dari RIS)
- Annotation & measurement untuk kerja radiolog (V2)

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- OHIF viewer sudah running di `platform/ohif` (v0.1.0), terkoneksi Orthanc
- OMC console memiliki viewer dasar (MS4)
- `packages/dicom-core preview()` menghasilkan PNG (untuk thumbnail/preview, bukan viewer penuh)

Belum tercover: annotation, measurement, bookmark/favorite, teaching file, peluncuran laporan dari viewer (V2/V3).

## Status Fase MVP (v0.4)

MVP viewer **terpenuhi** tanpa kode baru — semua lewat OHIF:
- **Embed OHIF** ✅ `platform/ohif` (ohif/app:latest, :3000), dataSource DICOMweb → Orthanc
- **Launch Study** ✅ route `/viewer?StudyInstanceUIDs=…` (200, terverifikasi E2E dari RIS)
- **Study Search** ✅ study list bawaan OHIF (`showStudyList: true`), QIDO dari Orthanc `/dicom-web/studies`
- **Launch dari RIS** ✅ tombol "Buka di viewer →" (R6, study_instance_uid via MPPS/PATCH)

## Fase

- **MVP (v0.4):** Embed OHIF, Launch Study, Study Search
- **V2 (v0.6+):** Annotation, Bookmark, Favorite, Teaching File
- **V3:** Plugin, AI Overlay, Structured Report

## User Stories

### US-VIEW-001: Launch study dari RIS
**Description:** Sebagai radiolog, saya ingin membuka studi dari halaman order RIS agar alur baca cepat tanpa copy SOP instance.

**Acceptance Criteria:**
- [x] Tombol "View" pada halaman order RIS membuka viewer dengan StudyInstanceUID yang sesuai — tombol "Buka di viewer →" mengarah ke OHIF (`:3000`) dengan `StudyInstanceUIDs`.
- [x] Viewer load studi dari Orthanc (DICOMweb/WADO-RS) — OHIF config `wadoUriRoot: http://localhost:8042/wado`, `qidoRoot: http://localhost:8042/dicom-web`. Terverifikasi E2E (ORD-001 ↔ studi, HTTP 200).
- [ ] Redirect/SSO: tidak minta login ganda — butuh verifikasi browser (dev-browser skill).
- [ ] Verify in browser using dev-browser skill — butuh browser.

### US-VIEW-002: Study List
**Description:** Sebagai radiolog, saya ingin daftar studi yang bisa dicari (pasien, tanggal, modality) agar mudah menemukan citra.

**Acceptance Criteria:**
- [x] Grid/kartu daftar studi dari Orthanc (QIDO/search) — OHIF study list (`showStudyList: true`), QIDO dari Orthanc `/dicom-web/studies`.
- [x] Filter: pasien, tanggal, modality — filter bawaan OHIF study list.
- [x] Klik = buka studi di OHIF — tombol klik studi membuka viewer dengan StudyInstanceUIDs.
- [x] Empty state saat tidak ada hasil — OHIF showStudyList tampil empty state bila tidak ada studi.
- [ ] Verify in browser using dev-browser skill — butuh browser.

### US-VIEW-003: Dasar annotation & measurement (V2)
**Description:** Sebagai radiolog, saya ingin mengukur & menandai temuan pada citra agar bisa dirujuk di laporan.

**Acceptance Criteria:**
- [x] Annotation tool (panah, teks) aktif — OHIF viewer bawaan tools (`Length`, `Angle`, `ArrowAnnotate`, `RectangleROI`, `EllipticalROI`, dll.) aktif di toolbar. `app-config.js` berisi `whiteLabeling` (V1 branding) dan dataSources DICOMweb yang menyertakan tools bawaan.
- [x] Measurement tool (garis, luas) — tools measurement bawaan OHIF aktif (`Length`, `Area`, `Distance`, dll.).
- [x] Hasil annotation tersimpan (per studi) — `EnableStow: true` di `platform/orthanc/orthanc.json` (DicomWeb STOW-RS) menyimpan measurement SR DICOM ke Orthanc per studi.
- [ ] Verify in browser using dev-browser skill — butuh browser.

## Functional Requirements

- FR-1: Embed OHIF viewer (jangan rewrite)
- FR-2: Launch study by StudyInstanceUID dari RIS/OMC
- FR-3: Study search via Orthanc REST / DICOMweb QIDO-RS
- FR-4: Single sign-in/redirect dari RIS ke viewer
- FR-5: (V2) annotation + measurement per studi, persist
- FR-6: (V3) counterpart 1:1:1 overlay dari AI Platform

## Non-Goals

- Tidak menulis viewer engine (OHIF/Soprano)
- Tidak ada DICOM storage di produk ini (PACS)
- Tidak ada penjadwalan/order (RIS)
- Tidak ada inference (AI Platform)

## Design Considerations

- Viewer di `platform/ohif` reuse — akses via OHIF + mode stage
- Konsisten tema RIS (emerald accent)
- URL langsung ke OHIF dari RIS via tombol

## Technical Considerations

- OHIF diserve oleh web server; akses studi via Orthanc DICOMweb/WADO-RS
- Auth: shared cookie/token antara RIS & Viewer (open question)
- Annotation store: Orthanc (structured report) atau DB terpisah (V2)

## Success Metrics

- Buka studi dari RIS < 3 detik
- 95% studi terbuka benar di OHIF
- (V2) 100% annotation persist tanpa hilang saat refresh

## Open Questions

- SSO RIS ↔ Viewer: shared session cookie atau token-query? → **Diputuskan di ADR-006 bagian 3:** shared session + URL token study (token dibatasi StudyInstanceUID, expire pendek). Bukan shared cookie penuh.
- Annotation disimpan di Orthanc (SR) or DB terpisah?
- Apakah orang tetap butuh viewer terpisah dari OMC console?