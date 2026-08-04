# Troubleshooting & Operasional

Pedoman diagnosis — urutkan dari kemungkinan tersering.

> Prinsip: "alat tidak support PACS" hampir selalu bukan karena Orthanc. Urutan penyebab: **(1) konfigurasi salah → (2) implementasi DICOM alat tidak standar → (3) vendor belum pernah diuji dengan PACS ini → (4) bug firmware**. Uji dengan DCMTK *sebelum* menyalahkan PZDR.

## 1. Gejala → Penyebab → Cek

| Gejala | Kemungkinan Penyebab | Cara Cek |
|---|---|---|
| **C-ECHO gagal** | AE title salah | `docker logs pacs-orthanc` → cari "Unknown Calling AE" atau penolakan association |
| | AE PZDR belum "terdaftar" | Orthanc juga longgar (DicomCheckCallingAet=false) — cek tetap perlu |
| | IP/port salah | `echoscu` dari mesin lain; `Test-NetConnection <ip> -Port 4242` |
| | Firewall | buka 4242 di host + outbound di PZDR |
| **C-ECHO OK, C-STORE gagal** | Transfer syntax tidak cocok | log Orthanc "presentation context"; cek SOP class & transfer syntax PZDR |
| | SOP class vendor tidak standar | lihat log; bandingkan dengan standar DICOM |
| **Timeout / tidak terhubung** | Firewall blokir port | `ping` OK ≠ port terbuka; cek firewall di kedua sisi |
| **Studi dapat tapi kosong/tidak muncul di viewer** | Metadata (PatientName/UID) tidak lengkap | `dcmdump` file di PZDR; cek di UI Orthanc |
| **Worklist tidak muncul** (RIS) | PZDR hanya dukung Storage, bukan MWL | manual ≠ Conformance Statement — tanya vendor SOP/transfer syntax |
| **Studi terkirim berulang** | Auto send + kirim manual ganda | pilih salah satu mode; cek queue |

## 2. Perintah DCMTK (uji dari mesin apa pun)

```bash
echoscu  -aec ORTHANC -aet TEST <ip> 4242         # verify koneksi
storescu -aec ORTHANC -aet TEST <ip> 4242 file.dcm # kirim studi
findscu  -aec ORTHANC -aet TEST <ip> 4242 ...      # query
dcmdump  file.dcm                                   # inspeksi tag DICOM
```

Sukses di sini + gagal di PZDR → masalahnya pengaturan PZDR. Gagal di sini juga → stack/jaringan.

## 3. Log Orthanc

```bash
docker logs -f pacs-orthanc
docker logs pacs-orthanc | grep -iE "echo|store|reject|fail|error"
```

## 4. Backup / Restore

Backup = 2 bagian:
- **Storage DICOM**: folder `data/orthanc` = seluruh file gambar.
- **Index (PostgreSQL)**: `pg_dump` volume `pacs-db-data`.

```bash
# backup index
docker exec pacs-db pg_dump -U orthanc -d orthanc > backup-index.sql
# copy storage
tar czf backup-storage.tar.gz data/orthanc
```

Restore: stop stack, tarik `data/orthanc`, `psql` restore `backup-index.sql`, start stack.

## 5. Keamanan

Konfigurasi saat ini (`AuthenticationEnabled: false`) **hanya untuk lab**. Sebelum produksi:

1. Nyalakan autentikasi REST di `orthanc.json`:
   - `"AuthenticationEnabled": true`
   - isi `"RegisteredUsers": { "user": "password" }`
   - OHIF perlu kredensial → sesuaikan `app-config.js`/proksi.
2. Batasi DICOM: set `DicomCheckCallingAet: true` dan daftarkan hanya AE PZDR yang diizinkan.
3. Jangan expose `8042`/`3000` ke internet tanpa reverse proxy + auth.

## 6. Menyala-nyalakan

```bash
docker compose up -d            # mulai / setelah restart host
docker compose restart orthanc  # setelah ubah orthanc.json
docker compose ps               # cek status (health)
```