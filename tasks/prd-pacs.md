# PRD: ORP PACS (Picture Archiving and Communication System)

## Introduction

Produk PACS adalah pembungkus Orthanc sebagai engine penyimpanan DICOM: storage, routing, archive, compression, lifecycle, replication, backup, monitoring. Bukan menulis engine sendiri — Orthanc + PostgreSQL + OHIF sudah berjalan di `platform/` (v0.1.0).

## Goals

- Penyimpanan DICOM permanen & konsisten (Orthanc + PostgreSQL index)
- Backup storage + index terjadwal (sudah ada `scripts/backup.sh`)
- Healthcheck & monitoring (sudah ada `scripts/health.sh`)
- Routing/forwarding studi antar node (V2)
- Lifecycle & storage tiering (V2–V3)

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- Orthanc + PostgreSQL + OHIF compose di `platform/orthanc` (v0.1.0)
- `scripts/check.sh` (5/5), `health.sh`, `backup.sh` (storage+index), `restore.sh` (disabled by default)
- Data di `data/orthanc`
- ADR: Orthanc sebagai satu-satunya penyimpan DICOM

Belum tercover: routing rules, forwarding, storage tiering, compression, replication, cluster, object storage, monitoring dashboard.

## Fase

- **MVP (v0.2):** Orthanc, Postgres, OHIF, Backup, Healthcheck
- **V2 (v0.4+):** Storage Tier, Compression, Forwarding, Rules, Replication
- **V3:** Cluster, Multi Node, Cloud Storage, Object Storage

## User Stories

### US-PACS-001: Backup terjadwal otomatis
**Description:** Sebagai admin, saya ingin backup storage + index berjalan otomatis terjadwal agar data aman tanpa intervensi manual.

**Acceptance Criteria:**
- [x] Cron/systemd timer menjalankan `scripts/backup.sh` harian — `scripts/backup.sh` ada; backup otomatis harian via systemd user timer `orp-backup.timer` (cek: `systemctl --user status orp-backup.timer`).
- [x] Backup berisi `data/orthanc` + index PostgreSQL — `scripts/backup.sh` mencadangkan `data/orthanc` + PostgreSQL index (via `pg_dump`).
- [x] Restore teruji sekali (dry-run) dari backup terbaru — `scripts/restore.sh --run <storage> <sql>` ada; restore teruji (2 studi utuh setelah restore, verified di changelog v0.2.0).
- [x] Log backup tercatat di `data/backups/` — `scripts/backup.sh` mencatat log di `data/backups/`.
- [x] Verify restore: studi tetap terbaca di Orthanc setelah restore — diverifikasi pada release v0.2.0 (2 studi utuh setelah restore).

### US-PACS-002: Dashboard monitoring
**Description:** Sebagai admin, saya ingin melihat status health & disk usage agar bisa antisipasi sebelum down.

**Acceptance Criteria:**
- [x] Halaman/endpoint health (5 check: orthanc, postgres, ohif, disk, service) — `scripts/health.sh --json` output mencakup 5 check: `orthanc_http`, `ohif_http`, `postgres`, `service`, `studies`, `disk`. Terverifikasi JSON output valid.
- [x] Tampil disk usage storage DICOM — section `disk` di `health.sh --json` menampilkan `dicom_storage_mb` (storage DICOM) dan `host` (disk usage % + avail GB).
- [ ] Verify in browser using dev-browser skill — butuh browser.
- [x] Monitoring dashboard halaman di OMC console — halaman `/monitoring` di OMC console (`products/omc/console/src/routes/monitoring/+page.svelte`) menampilkan 5 check + disk usage dari endpoint `GET /pacs/health` OMC API.
- [x] Endpoint `GET /pacs/health` di OMC API — menjalankan `scripts/health.sh --json` dan mengembalikan JSON 5 check.

### US-PACS-003: Routing rule DICOM (V2)
**Description:** Sebagai admin, saya ingin aturan forwarding studi ke node/OMC lain agar studi terdistribusi.

**Acceptance Criteria:**
- [ ] Aturan berbasis metadata (modality, AET, body part) — *belum diimplementasikan (V2 OMC, menunggu backlog)*.
- [ ] Forwarding via C-STORE ke node tujuan — *belum diimplementasikan*.
- [ ] Log hasil forwarding — *belum*.
- [ ] Test rule engine lulus — *belum*.

## Functional Requirements

- FR-1: Orthanc sebagai single source of truth DICOM storage
- FR-2: PostgreSQL index Orthanc (bukan SQLite)
- FR-3: Backup storage + index; restore disabled-by-default (keamanan)
- FR-4: Healthcheck 5/5 (scripts/health.sh)
- FR-5: OHIF viewer embedded, akses studi via Orthanc REST :8042
- FR-6: Logging ke `data/orthanc` + `data/backups`
- FR-7: (V2) forwarding rule engine C-STORE

## Non-Goals

- Tidak menulis engine DICOM sendiri (pakai Orthanc)
- Tidak ada UI manajemen penuh di MVP (cukup healthcheck + backup)
- Tidak ada clustering di MVP (V3)
- Tidak ada object storage di MVP (V3)

## Design Considerations

- Compose di `platform/orthanc` — jangan refactor besar tanpa ADR baru
- Volume data di `data/orthanc` — jangan commit isi ke git
- Backups di `data/backups/` — .gitignore, bukan repo

## Technical Considerations

- Orthanc (C++ engine) + PostgreSQL + OHIF (viewer)
- DICOM port 4242, REST 8042 — jangan ubah tanpa koordinasi Integration/OMC
- Restore harus manual & sadar: jangan auto-restore saat boot
- Pynetdicom/dcmtk hanya untuk tooling, bukan pengganti Orthanc

## Success Metrics

- Backup harian sukses 100% selama 2 minggu berturut
- Restore < 15 menit (studi kembali terbaca)
- Orthanc uptime > 99% di dev

## Open Questions

- Storage tiering: butuh hot/warm/cold atau cukup disk besar?
- Backup ke remote (S3/rsync) kapan?
- Monitoring dashboard: Grafana atau cukup healthcheck script?
