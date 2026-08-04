# Panduan Install PZDR + Konfigurasi PACS (Klik-per-Klik)

Tujuan: PZDR (Windows DR) → kirim studi ke Orthanc PACS yang sudah jalan di `10.205.136.1`.
Ikuti berurutan. Jangan skip tahap verifikasi.

---

## Sebelum Mulai (Prasyarat)

| Item | Nilai / Aksi |
|---|---|
| Mesin DR Windows | Win 7/10/11, RAM ≥8GB, disk ≥512GB, NIC 1000M |
| IP statis workstation PZDR | Catat (mis. `192.168.2.21`). JANGAN pakai DHCP acak |
| IP mesin PACS (Docker host) | `10.205.136.1` (sesuaikan bila berubah) |
| Instalasi PACS | Stack Orthanc sudah `docker compose up -d` & healthy |
| Manual | `pzdr/PZDR User Manual_V2.0.14.0 (FORERMED).pdf` §2.2, §4.6.4 |

### Verifikasi koneksi dari workstation PZDR (sebelum install)

```powershell
# PowerShell di Windows PZDR:
ping 10.205.136.1
Test-NetConnection 10.205.136.1 -Port 4242   # harus: TcpTestSucceeded : True
```

> Jika `ping` ok tapi port gagal → firewall Windows PZDR atau host Docker memblokir. Selesaikan dulu — tanpa ini, PZDR tidak akan pernah tersambung.

---

## Tahap 1 — Install PZDR

Installer: `pzdr/PZDR_V2.0.14.2024102102.exe` (kopi ke workstation PZDR dulu, atau akses dari folder bersama).

1. **Double-click** installer.
2. Pilih **bahasa instalasi**.
3. Pilih **lokasi instalasi** (disarankan default).
4. Centang **buat shortcut desktop**.
5. Klik konfirmasi → tunggu progress selesai.
6. **Setelah install selesai** (untuk V2.0.14 > 20220810):
   - Buka folder desktop **PZDR** → folder **runtime**.
   - Install **vc2015_x86** (skip jika sudah ada).
   - (Bila diminta, install **.NET Framework 4.6** — prasyarat, skip jika sudah.)
7. Buka PZDR → pastikan software **bisa mendeteksi detector** (auto-detect, §3.1A).

> Saat pertama kali, periksa mode detector & generator di Configuration Tools (§3.1B/C) bila perlu — ini di luar lingkup PACS tapi wajib sebelum eksposur.

---

## Tahap 2 — Buka Configuration Tools

1. Buka aplikasi **PZDR**.
2. Klik tombol **Configure** (ikon settings).
3. Masukkan password: **`1`** (default sesuai manual §4.6.1).
4. Anda masuk ke layar konfigurasi (System Configuration).

---

## Tahap 3 — Konfigurasi PACS (4.6.4 PACS Configuration)

1. Buka tab/halaman **PACS Configuration** (di menu Configuration Tools).
2. Isi **persis** nilai berikut:

| Field PZDR | Nilai | Keterangan |
|---|---|---|
| **Host AETitle** | `PZDR_DR1` | AETitle PZDR sendiri — bebas tapi unik di jaringan |
| **AETitle** | `ORTHANC` | AETitle PACS — **harus sama persis** (case-sensitive) |
| **Hostname** | `10.205.136.1` | IP mesin Docker (bukan `localhost`) |
| **Port** | `4242` | Port DICOM Orthanc |
| **Auto send** | **OFF** | Kirim manual dulu (untuk uji coba) |

3. Klik **Test** (tombol tes).
   - **Sukses** = PACS menerima koneksi (C-ECHO berhasil) → lanjut.
   - **Gagal** → lihat tabel troubleshooting di bawah, jangan lanjut dulu.
4. Klik **Modify** → PZDR akan meminta **restart** → konfirmasi.
5. Buka ulang PZDR. Konfigurasi PACS aktif.

### Troubleshooting Test gagal

| Gejala | Cek |
|---|---|
| Timeout | `Test-NetConnection 10.205.136.1 -Port 4242`; firewall host Docker; container `pacs-orthanc` hidup? |
| Association Rejected | AETitle tidak sama persis; lihat `docker logs pacs-orthanc` — cari "Unknown Calling AE" / "Called AE" |
| Port salah | Pastikan `4242` (bukan `104` — Orthanc default 4242) |

---

## Tahap 4 — Kirim Studi Pertama (Manual)

1. Lakukan pemeriksaan (atau pakai **TEST mode** detector tanpa X-ray, §3.1B).
2. Buka **Database** → pilih pemeriksaan.
3. Klik tombol **upload PACS** — akan tampil di **PACS Transmission Queue** (§4.5.8).
4. Di queue, pantau status:
   - **Berhasil** → studi masuk PACS.
   - **Gagal/antre** → tombol **Restart** untuk ulang; **Pause** untuk menahan.
5. Verifikasi di sisi PACS:

```bash
# di mesin Docker:
curl -s http://localhost:8042/studies          # daftar study ID
curl -s http://localhost:8042/patients | python3 -m json.tool   # daftar pasien
```

6. Buka **OHIF viewer** `http://10.205.136.1:3000` → studi muncul → klik → gambar tampil.

> Kiri pasien = `docker logs -f pacs-orthanc` saat PZDR mengirim → terlihat log C-STORE masuk.

---

## Tahap 5 — Uji Pemulihan (wajib sebelum Auto-send)

1. **Matikan sementara** PACS: `docker compose stop` (di mesin Docker).
2. Kirim pemeriksaan dari PZDR → queue akan **menahan** data (status gagal/antre).
3. **Hidupkan kembali**: `docker compose start` → tunggu healthy.
4. Di Transmission Queue PZDR klik **Restart** → pengiriman lanjut & sukses.

Hasil diharapkan: **tidak ada gambar hilang** saat PACS offline — PZDR menahan dan mengirim ulang.

---

## Tahap 6 — Aktifkan Auto-send (setelah stabil)

Hanya setelah 1–2 minggu alur manual tervalidasi (lihat `docs/ACCEPTANCE-TEST.md`):

1. Configuration Tools → 4.6.4 PACS Configuration.
2. **Auto send → ON**.
3. **Modify** → restart PZDR.
4. Pantau Transmission Queue setiap pagi: harus **kosong** (semua terkirim otomatis).

---

## Tahap 7 — (Opsional) Integrasi Worklist RIS

Jika nanti mau terima order dari RIS (MWL), ada konfigurasi terpisah — lihat `docs/PZDR-INTEGRATION.md` §Tahap 7 dan bagian Worklist (4.6.6) di manual. **Jangan campur** dengan konfigurasi PACS Storage di atas; Worklist punya AETitle/IP/port sendiri dari server RIS.

---

## Referensi Manual

- §2.2 Installation Procedure
- §3.1 Software Configuration (detector, mode, generator)
- §4.6.4 PACS Configuration
- §4.5.8 PACS Transmission Queue
- §4.6.6 Work List Configuration (untuk RIS nanti)
