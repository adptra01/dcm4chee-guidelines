# Panduan Integrasi PZDR → Orthanc

Urutan implementasi bertahap. Jangan lompat sebelum tahap sebelumnya hijau.

## Tahap 1 — Install PZDR (Windows DR)

Installer: `pzdr/PZDR_V2.0.14.2024102102.exe` (manual: `pzdr/PZDR User Manual_V2.0.14.0 (FORERMED).pdf`).

1. Install **.NET Framework 4.6** dan **vc_2015** bila belum ada (prasyarat).
2. Jalankan installer PZDR. Dukungan OS: Windows 7/10/11.
3. Catat **IP static** workstation PZDR (mis. `192.168.x.21`). IP statis wajib — PACS mengunci by IP/AE.

## Tahap 2 — Pastikan Network

```bash
# dari workstation PZDR ke mesin Docker:
ping <docker-host-ip>
# cek port 4242 terbuka (di Windows PowerShell):
Test-NetConnection <docker-host-ip> -Port 4242
```

Pastikan firewall Windows PZDR mengizinkan **outbound** ke `docker-host:4242`, dan host Docker membuka port `4242/8042/3000`.

## Tahap 3 — Konfigurasi PZDR (PACS)

1. Buka **Configuration Tools** → password: `1`.
2. Masuk **4.6.4 PACS Configuration**:

| Field PZDR | Nilai |
|---|---|
| Host AETitle | `PZDR_DR1` |
| AETitle | `ORTHANC` |
| Hostname | IP mesin Docker |
| Port | `4242` |
| Auto send | **OFF** (fase awal) |

3. Klik **Test** → C-ECHO harus **Success**.
4. Klik **Modify** → restart PZDR agar berlaku.

## Tahap 4 — Kirim Studi Pertama (Manual)

1. Ambil satu pemeriksaan, di **Database** pilih exam.
2. Klik tombol **PACS upload** (via **PACS Transmission Queue**, §4.5.8).
3. Cek status di queue: harus sukses.
4. Verifikasi di OHIF Viewer: `http://<docker-host>:3000` → study muncul, atau cek via REST `curl http://localhost:8042/studies`.

## Tahap 5 — Uji Pemulihan (Transmission Queue)

Uji ketahanan jaringan — bagian penting:

1. Matikan jaringan/stop container Orthanc.
2. Kirim exam → queue akan **pause/menahan** data.
3. Hidupkan kembali → tekan **Restart** → pengiriman lanjut sukses.

Jika ini bekerja, gambar tidak hilang saat PACS offline — data aman menunggu PACS kembali.

## Tahap 6 — Aktifkan Auto Send

Telat secara bertahap setelah semua alur tervalidasi 1–2 minggu:
1. Kembali ke **4.6.4 PACS Configuration**.
2. Auto send → **ON** → Modify → restart.
3. Pantau **PACS Transmission Queue** tiap pagi: harus kosong (tidak ada antrean tergantung).

## Tahap 7 — Risiko Simbolis: Pastikan Viewer Jalan

Buka `http://<docker-host>:3000` di browser — study list muncul, klik study → gambar tampil. Jika dari mesin lain, ganti `localhost` dengan IP Docker di `ohif/app-config.js` lalu `docker compose restart ohif`.

## Tips Validasi Tanpa PZDR

Gunakan DCMTK dari mesin mana pun sebagai "PZDR palsu" (SCU) untuk menguji Orthanc:

```bash
echoscu  -aec ORTHANC -aet TEST <ip> 4242
storescu -aec ORTHANC -aet TEST <ip> 4242 sample.dcm
```

Lakukan ini **sebelum** menyentuh PZDR — kalau gagal di sini, masalahnya di jaringan/stack, bukan di PZDR.