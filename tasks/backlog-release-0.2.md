# Backlog Release v0.2 — RIS + PACS Berjalan

> **Status: SELESAI** (tag `v0.2.0`, 2026-08-06). Dokumen dipertahankan sebagai arsip urutan kerja + verifikasi.

Sumber: `tasks/prd-ris.md` + `tasks/prd-pacs.md`. Kriteria kelulusan release: RIS (admin pasien/order/prosedur/laporan) + PACS (backup auto, monitoring) berjalan end-to-end, studi bisa masuk Orthanc dan dilihat (viewer dasar).

## Urutan kerja (dependensi →)

### Batch 1 — Fondasi RIS (setelah dashboard)

| ID | Story | PRD ref | Prioritas | Status |
|---|---|---|---|---|
| R1 | CRUD Doctor (model+migration+halaman) | US-RIS-002 | P1 | ✅ |
| R2 | CRUD Procedure (master prosedur) | US-RIS-003 | P1 | ✅ |
| R3 | Order form: pilih patient + doctor + procedure | FR-1/2/3 | P1 | ✅ |

### Batch 2 — Alur pemeriksaan

| ID | Story | PRD ref | Prioritas | Status |
|---|---|---|---|---|
| R4 | Halaman Worklist operasional (filter status, update arrived/started/completed) | US-RIS-004 | P1 | ✅ |
| R5 | Halaman Report per order (findings+impression, draft→final) | US-RIS-005 | P1 | ✅ |
| R6 | Tombol "View" → buka studi di OHIF (jembatan RIS→PACS) | US-VIEW-001 (viewer) | P1* | ✅ |

> *R6 dinaikkan P2→P1 (hasil review): satu-satunya item yang membuktikan alur RIS→PACS→Viewer nyambung end-to-end dan jadi syarat Batch 4.

### Batch 3 — PACS hardening

| ID | Story | PRD ref | Prioritas | Status |
|---|---|---|---|---|
| B1 | Backup terjadwal otomatis (systemd timer daily 00:00) | US-PACS-001 | P1 | ✅ |
| B2 | Dashboard monitoring (health 5 check + disk usage) | US-PACS-002 | P2 | ⏳ (opsional, backlog v0.4) |
| B3 | Restore dry-run teruji dari backup terbaru | US-PACS-001 | P1 | ✅ |

### Batch 4 — Verifikasi release

- [x] E2E: buat pasien → order (prosedur) → worklist muncul → status arrived → studi di Orthanc → buka di OHIF
- [x] Backup berjalan otomatis + restore teruji (2 studi utuh)
- [x] Test: 96 passed total (RIS 61 · Integration 18 · OMC 8 · AI 5 · dicom-core 4) + PACS health 5/5
- [x] Tag `v0.2.0` + CHANGELOG

## Estimasi

- Batch 1–2: ~2 sesi · Batch 3: ~1 sesi · Total 3 sesi sebelum tag v0.2.0 — terpenuhi.

## Di luar scope v0.2 (ke release berikutnya)

- OMC kirim DICOM (v0.3) — **sudah berjalan**: C-STORE + `/settings` C-ECHO + halaman Settings console
- Viewer penuh + annotation (v0.4/v0.6)
- MWL/MPPS aliran penuh ke RIS (integration sudah punya SCP :4243/:4244; OMC belum kirim MPPS)
- Reporting template & signature (v0.6)
- Auth `X-API-Key` untuk OMC & AI (ADR-006 — wajib sebelum produksi)
- SATUSEHAT adapter (jadwal kepatuhan — lihat `tasks/prd-integration.md`)
