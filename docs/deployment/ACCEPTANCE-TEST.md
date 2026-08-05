# Acceptance Test Plan (ATP)

Checklist uji integrasi PZDR → Orthanc. Jalankan berurutan, centang saat lolos.
Semua data uji memakai pasien dummy. Jangan pakai data pasien asli.

## A. Stack & Infrastruktur

| # | Uji | Harapan | ✓ | Catatan |
|---|-----|---------|---|---------|
| A1 | `docker compose up -d` semua container up & healthy | 3 container running | | |
| A2 | `http://<host>:8042/orthanc/hello` | respon OK | | |
| A3 | OHIF `http://<host>:3000` | halaman study list | | |
| A4 | `docker exec pacs-db pg_isready` | accepting | | |

## B. DICOM (dari mesin dengan dcmtk / workstation PZDR)

| # | Uji | Harapan | ✓ |
|---|-----|---------|---|
| B1 | `echoscu -aec ORTHANC -aet TEST <ip> 4242` | Echo Success | |
| B2 | `storescu -aec ORTHANC -aet TEST <ip> 4242 file.dcm` | Store Success | |
| B3 | Cek studi muncul di REST `curl http://<host>:8042/studies` | study ID terdaftar | |
| B4 | Studi muncul di OHIF viewer | gambar terbuka | |
| B5 | Metadata pasien benar di Orthanc UI | nama/UID sesuai file asli | |

## C. Pemulihan (Transmission Queue Robustness)

| # | Uji | Harapan | ✓ |
|---|-----|---------|---|
| C1 | Stop Orthanc, kirim studi → PZDR | queue menahan, tidak hilang | |
| C2 | Start Orthanc, Restart kiriman | pengiriman lanjut & sukses | |
| C3 | Kirim banyak studi (mis. 50+ image) | semua masuk tanpa gagal | |

## D. Alur PZDR sungguhan

| # | Uji | Harapan | ✓ |
|---|-----|---------|---|
| D1 | Test di 4.6.4 Configuration (C-ECHO) | Sukses | |
| D2 | Pemeriksaan DR → upload manual via Transmission Queue | muncul di Orthanc + OHIF | |
| D3 | 10 studi berturut-turut manual | 0 gagal, queue kosong | |

## E. Riset Mosio: Validasi internal dati Rampung

Setelah semua hijau (≈1–2 minggu monitoring):

| # | Uji | Harapan | ✓ |
|---|-----|---------|---|
| E1 | Aktifkan Auto send | kiriman otomatis | |
| E2 | Pantau queue 5 hari kerja | selalu kosong, 0 gagal | |
| E3 | Backup penuh (storage + index) | restore diuji 1x |    |

## Lolos / Tidak:
- [ ] Semua A → [ ] Semua B → [ ] Semua C → [ ] Semua D → [ ] (opsional) E
- **Keputusan Go-Live**: LOLOS ________________ / TUNDA ________________
- Tanda tangan: ___________ Tanggal: ___________

> Catatan: Garivasi hari. Hanya lanjut ke tahap berikut bila tahap sebelumnya hijau penuh.