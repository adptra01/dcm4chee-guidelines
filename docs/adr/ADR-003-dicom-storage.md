# ADR-003 — Penyimpanan DICOM: Orthanc satu-satunya (Core Rule)

- Status: **Accepted** (2026-08-05)
- Konteks: mencegah duplikasi data DICOM dan kebingungan "file gambar ada di
  mana" ketika RIS/OMC/AI berkembang.

## Keputusan

**Orthanc adalah satu-satunya penyimpan DICOM.** Semua akses gambar lewat
Orthanc REST / DICOMweb. (Ini adalah Core Rule ADR-001, dirinci di sini.)

### Yang diizinkan
- OMC: buffer sementara saat ingest (dihapus setelah tersimpan ke Orthanc).
- OHIF/AI: membaca via DICOMweb (`/dicom-web`, WADO-RS) dari Orthanc.
- Backup: `scripts/backup.sh` (storage `data/orthanc` + pg_dump index) — satu
  titik backup, bukan per-aplikasi.

### Yang TIDAK diizinkan
- ❌ FastAPI menulis file DICOM permanen ke filesystem sendiri.
- ❌ Laravel meng-host file DICOM.
- ❌ Svelte console mengakses filesystem DICOM.

## Alasan

- Satu source of truth untuk gambar → tidak ada mismatch antar produk.
- Backup & restore terpusat (MS0: `scripts/backup.sh`, `restore.sh`).
- Mengganti PACS lebih mudah (ADR-004) karena hanya antarmuka REST/DICOMweb.

## Konsekuensi

- Semua produk butuh koneksi ke Orthanc (fault-tolerance perlu didesain).
- Storage data orthanc (`data/orthanc`) + index PostgreSQL wajib di-backup
  bersamaan (konsisten).

## Lihat juga

- ADR-001 (Core Rule), ADR-004 (adapter PACS)
