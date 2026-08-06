# Backlog Release v0.2 — RIS + PACS Berjalan

Sumber: `tasks/prd-ris.md` + `tasks/prd-pacs.md`. Kriteria kelulusan release: RIS (admin pasien/order/prosedur/laporan) + PACS (backup auto, monitoring) berjalan end-to-end, studi bisa masuk Orthanc dan dilihat (viewer dasar).

## Urutan kerja (dependensi →)

### Batch 1 — Fondasi RIS (setelah dashboard)

| ID | Story | PRD ref | Prioritas |
|---|---|---|---|
| R1 | CRUD Doctor (model+migration+halaman) | US-RIS-002 | P1 |
| R2 | CRUD Procedure (master prosedur) | US-RIS-003 | P1 |
| R3 | Order form: pilih patient + doctor + procedure | FR-1/2/3 | P1 |

### Batch 2 — Alur pemeriksaan

| ID | Story | PRD ref | Prioritas |
|---|---|---|---|
| R4 | Halaman Worklist operasional (filter status, update arrived/started/completed) | US-RIS-004 | P1 |
| R5 | Halaman Report per order (findings+impression, draft→final) | US-RIS-005 | P1 |
| R6 | Tombol "View" → buka studi di OHIF (jembatan RIS→PACS) | US-VIEW-001 (viewer) | P2 |

### Batch 3 — PACS hardening

| ID | Story | PRD ref | Prioritas |
|---|---|---|---|
| P1 | Backup terjadwal otomatis (cron/systemd) | US-PACS-001 | P1 |
| P2 | Dashboard monitoring (health 5 check + disk usage) | US-PACS-002 | P2 |
| P3 | Restore dry-run teruji dari backup terbaru | US-PACS-001 | P1 |

### Batch 4 — Verifikasi release

- [ ] E2E: buat pasien → order (prosedur) → worklist muncul → status arrived → studi di Orthanc → buka di OHIF
- [ ] Backup berjalan otomatis + restore teruji
- [ ] Test total ≥ 51 RIS + PACS health check 5/5
- [ ] Tag `v0.2.0` + CHANGELOG

## Estimasi kasar

- Batch 1–2: ~2 sesi kerja (RIS)
- Batch 3: ~1 sesi (PACS infra)
- Total: 3 sesi sebelum tag v0.2.0

## Di luar scope v0.2 (ke release berikutnya)

- OMC kirim DICOM (v0.3) — sudah bisa C-STORE, tinggal polish UI
- Viewer penuh + annotation (v0.4/v0.6)
- MWL/MPPS aliran penuh ke RIS (v0.5, integration sudah ada SCP)
- Reporting template & signature (v0.6)
