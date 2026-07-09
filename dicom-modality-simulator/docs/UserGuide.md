# Panduan Pengguna

## Daftar Isi

1. [Test Connection](#test-connection)
2. [Refresh Worklist (Study Root)](#refresh-worklist-study-root)
3. [Modality Worklist (MWL)](#modality-worklist-mwl)
4. [Seed Data MWL](#seed-data-mwl)
5. [Kirim DICOM](#kirim-dicom)
6. [Image Converter](#image-converter)
7. [MPPS — Prosedur Berjalan](#mpps)
8. [Storage Commitment](#storage-commitment)
9. [C-MOVE — Retrieve Study](#c-move)
10. [Storage SCP — Jadi Penerima](#storage-scp)
11. [Dataset Viewer](#dataset-viewer)
12. [Cancel — Batalkan Operasi](#cancel)
13. [Graceful Close](#graceful-close)

---

## Test Connection

**Apa yang terjadi:** Aplikasi mengirim C-ECHO-RQ ke PACS. PACS harus membalas C-ECHO-RSP dengan status Success (0x0000). Ini seperti "ping" di dunia DICOM.

### Langkah

1. Isi field di panel **PACS Connection**:
   - **AE Title** — nama aplikasi kita (`SIMULATOR`)
   - **Called AE** — nama PACS tujuan (`DCM4CHEE`)
   - **Host** — IP PACS (`192.168.1.100` atau `localhost`)
   - **Port** — port DICOM PACS (`11112`)
2. Klik **[Test Connection]**
3. Tunggu beberapa detik

### Hasil

| Status | Artinya |
|--------|---------|
| ● Online (hijau) | Koneksi berhasil, PACS merespon |
| ○ Offline (abu-abu) | Koneksi gagal, cek log untuk detail |
| ⟳ Testing... (oranye) | Sedang proses, tunggu atau klik [Cancel] |

### Kalau Gagal

Lihat log di bagian bawah. Beberapa kemungkinan:

```
Could not associate with DCM4CHEE@192.168.1.100:11112
```
→ PACS tidak reachable. Cek `ping <host>` dan `telnet <host> 11112`.

```
C-ECHO failed: Status 0x0122
```
→ AE Title tidak dikenal PACS. Daftarkan AE di PACS (lihat Panduan Integrasi).

```
Connection error: timed out
```
→ Koneksi timeout 10 detik. Host tidak reachable atau port salah.

### Membatalkan

Kalo proses terlalu lama, klik **[Cancel]** — koneksi akan di-abort.

---

## Refresh Worklist (Study Root)

**Apa yang terjadi:** Aplikasi mengirim C-FIND-RQ (Study Root Query/Retrieve Information Model) ke PACS. PACS mengembalikan daftar study yang cocok dengan kriteria query (semua study, tanpa filter).

Protokol: `C-FIND` pada SOP Class `StudyRootQueryRetrieveInformationModelFind` (1.2.840.10008.5.1.4.1.2.2.1).

### Langkah

1. Pastikan status **● Online**
2. Pilih mode **Study Root** di radio button atas
3. Klik **[Refresh]**
4. Thread berjalan di background, GUI tetap responsif
5. Kalau selesai → tabel terisi, count berubah

### Kolom Tabel

| Kolom | Isi | Contoh |
|-------|-----|--------|
| Patient ID | ID pasien dari PACS | KNIX |
| Patient Name | Nama pasien | KNIX^KNIX |
| Date | Tanggal study | 20250709 |
| Modality | Jenis modality | MR, CT, US |
| Accession | Nomor aksesion | ACC001 |
| Description | Deskripsi study | *KNIX* |

### Kalau Gagal

```
Not connected. Test connection first.
```
→ Belum test connection. Klik [Test Connection] dulu.

```
Association failed
```
→ PACS menolak context C-FIND. Mungkin perlu daftarin AE dulu.

### Membatalkan

Klik **[Cancel]** — C-FIND akan di-abort.

---

## Modality Worklist (MWL)

**Apa yang terjadi:** Mengambil jadwal prosedur yang sudah dijadwalkan untuk modality. Berbeda dengan Study Root (yang menampilkan study **yang sudah selesai**), MWL menampilkan prosedur **yang akan datang / terjadwal**.

Di simulator ini, data MWL bersumber dari:
1. **Local JSON** (`mwl_data.json`) — hasil generate `seed_mwl.py`
2. **C-FIND ke PACS** — otomatis dicoba, kalau tidak ada response, fallback ke lokal

Protokol: `C-FIND` pada SOP Class `ModalityWorklistInformationModel - FIND` (1.2.840.10008.5.1.4.31).

### Langkah

1. Pastikan status **● Online**
2. Pilih mode **MWL (Scheduled)** di radio button atas
3. Klik **[Refresh]**
4. Aplikasi akan:
   - Coba query MWL ke PACS via C-FIND
   - Gabung dengan data lokal dari `mwl_data.json`
   - Tampilkan semua hasil di tabel

### Kolom Tabel

| Kolom | Isi | Contoh |
|-------|-----|--------|
| Patient ID | ID pasien | PAT001 |
| Patient Name | Nama pasien | BUDI^SUSANTO |
| Date | Tanggal jadwal | 20260709 |
| Modality | Modality | CT, MR, CR |
| Accession | Nomor aksesion | ACC001 |
| Description | Deskripsi prosedur | Abdomen CT |

### Data yang Tampil di Panel Detail

Setelah pilih pasien dari MWL, panel **Patient / Study Detail** menampilkan field tambahan:

| Field | Sumber | Contoh |
|-------|--------|--------|
| Req. Procedure | RequestedProcedureDescription | CT ABDOMEN |
| Scheduled SPS | ScheduledProcedureStepDescription | CT ABDOMEN WITH CONTRAST |
| Starts | ScheduledProcedureStepStart Date/Time | 20260709 1056 |
| Station AE | ScheduledStationAETitle | SIMULATOR |

### Catatan

- MWL data di `mwl_data.json` **hanya bisa diedit manual** atau regenerate via `seed_mwl.py`
- Data lokal akan **digabung** dengan hasil query PACS — tidak ada duplikasi (berdasarkan Accession Number)
- Kalau PACS tidak support MWL C-FIND, aplikasi hanya pakai data lokal tanpa error

---

## Seed Data MWL

Sebelum pakai MWL, generate dulu data dummy:

```bash
cd dicom-modality-simulator
.venv/bin/python seed_mwl.py
```

Hasil: 5 pasien dengan modality berbeda (CT, MR, CR, US, XA):

```
PAT001   BUDI^SUSANTO         CT   ACC001
PAT002   SITI^RAHMAWATI       MR   ACC002
PAT003   AGUS^PRAMONO         CR   ACC003
PAT004   DEWI^KUSUMA          US   ACC004
PAT005   HARI^PRASETYA        XA   ACC005
```

### Cara Kerja

- Script menghasilkan `mwl_data.json` di root project
- Data mencakup: nama, ID, DOB, modality, accession, procedure descriptions, UID, jadwal
- AE Title diset ke `SIMULATOR` — cocok dengan konfigurasi simulator
- Tanggal jadwal: hari ini
- Waktu: acak antara jam 08:00 - 16:59

### Customisasi

Edit `seed_mwl.py` untuk menambah/mengubah data pasien, atau langsung edit `mwl_data.json`.

---

## Kirim DICOM

**Apa yang terjadi:** Aplikasi baca file DICOM, merge dengan data pasien dari worklist (auto-fill), kirim ke PACS via C-STORE.

### Auto-fill Data Pasien

Kalau ada pasien yang dipilih dari worklist, data berikut otomatis dimasukkan ke file sebelum dikirim:

| Field | Sumber |
|-------|--------|
| PatientName | Dari worklist |
| PatientID | Dari worklist |
| AccessionNumber | Dari worklist |
| StudyDescription | Dari worklist |
| StudyInstanceUID | Dari worklist |

Ini memastikan study baru muncul di pasien yang benar di PACS.

### Langkah

1. (Opsional) Pilih pasien dari worklist
2. Klik **[Browse DICOM...]**
3. Pilih file `.dcm`
4. Klik **[Dump Dataset]** untuk lihat isi (opsional)
5. Klik **[Send to PACS]**

### Kalau Gagal

```
Not connected. Test connection first.
```
→ Klik [Test Connection] dulu.

```
C-STORE failed: Status 0xA900
```
→ AE tidak dikenal atau tidak punya izin menyimpan.

```
Association failed
```
→ Presentation context C-STORE tidak di-accept PACS. Cek apakah PACS support SOP Class file kamu.

### Catatan

- Simulator support **17 SOP Class** untuk C-STORE (CT, MR, US, XA, XRF, SC, DX, MG, NM, VL, PET, RT, dll)
- File akan dikirim apa adanya + auto-fill data pasien
- C-STORE dilakukan synchronous — thread menunggu response dari PACS

---

## Image Converter

**Apa yang terjadi:** Gambar biasa (JPG, PNG, BMP) dikonversi ke DICOM Secondary Capture Image Storage (SOP Class UID: 1.2.840.10008.5.1.4.1.1.7), lalu bisa dikirim ke PACS.

### Detail Konversi

| Aspek | Detail |
|-------|--------|
| SOP Class | Secondary Capture Image Storage |
| Modality | XC (External Camera) |
| Encoding | RGB, JPEG compressed |
| Ukuran | Sesuai gambar asli |
| Library | Pillow (baca) + pydicom (tulis) |

### Langkah

1. Pilih pasien dari worklist
2. Klik **[From Image...]**
3. Pilih file gambar
4. Dataset DICOM otomatis dibuat dengan:
   - Data pasien dari worklist
   - PixelData dari gambar
   - UID baru (Study, Series, SOP)
5. Klik **[Dump Dataset]** untuk verifikasi
6. Klik **[Send to PACS]**

### Contoh

```python
# Ini yang terjadi di belakang layar
from dicom.image import jpg_to_dicom

ds = jpg_to_dicom(
    "foto.jpg",
    patient_name="BUDI^UTOMO",
    patient_id="BUDI001",
    study_desc="Foto Pasien",
    modality="XC"
)
# ds sekarang siap dikirim via C-STORE
```

### Catatan

- Gambar akan muncul sebagai **Secondary Capture** di PACS — bukan gambar asli modality
- Cocok untuk: foto pasien, screenshot modality, dokumentasi
- Study akan terpisah dari study asli karena StudyInstanceUID berbeda

---

## MPPS

**Apa yang terjadi:** MPPS (Modality Performed Procedure Step) memberitahu PACS bahwa sebuah prosedur sedang berlangsung. Ini penting untuk workflow PACS — PACS jadi tahu kapan modality mulai dan selesai melakukan prosedur.

Protokol: `N-CREATE` (mulai) dan `N-SET` (selesai/gagal) pada SOP Class `ModalityPerformedProcedureStepSOPClass` (1.2.840.10008.3.1.2.3.3).

### Alur

```
[MPPS Start]  →  N-CREATE, status: IN PROGRESS
     ↓
  Kirim file  →  C-STORE (bisa beberapa kali)
     ↓
[Complete]    →  N-SET, status: COMPLETED
  atau
[Discontinue] →  N-SET, status: DISCONTINUED
```

### Langkah

1. Pilih pasien dari worklist
2. Klik **[MPPS Start]**:
   - Indikator berubah jadi **● IN PROGRESS** (oranye)
   - Tombol [Complete] dan [Discontinue] aktif
   - Tombol [Stg Cmt] non-aktif
3. Kirim file via C-STORE
4. Kalau selesai:
   - Klik **[Complete]** → indikator **● COMPLETED** (hijau)
   - Tombol [Stg Cmt] aktif
5. Kalau batal:
   - Klik **[Discontinue]** → indikator **● DISCONTINUED** (merah)

### Request N-CREATE

Dataset yang dikirim ke PACS:

```
PerformedProcedureStepID: 001
PerformedStationAETitle: SIMULATOR
PerformedProcedureStepStartDateTime: 2025-07-09 02:21:50
PerformedProcedureStepStatus: IN PROGRESS
PatientName: BUDI^UTOMO
PatientID: BUDI001
ScheduledStepAttributesSequence:
  > StudyInstanceUID: 1.2.3.4...
PerformedProcedureStepDescription: Foto Pasien
```

### Kalau Gagal

```
MPPS N-CREATE: 0x0110
Error Comment: java.lang.NullPointerException
```

**Penyebab (di dcm4chee):**
- Service MPPS tidak terdeploy
- Konfigurasi LDAP tidak lengkap
  
**Solusi:** Lihat Panduan Integrasi DCM4CHEE → Setup MPPS.

---

## Storage Commitment

**Apa yang terjadi:** Setelah file terkirim, kita minta PACS untuk "commit" — PACS harus konfirmasi bahwa file sudah diterima dan disimpan dengan aman. Ini penting untuk kepastian hukum/medis.

Protokol: `N-ACTION` pada SOP Class `StorageCommitmentPushModelSOPClass` (1.2.840.10008.1.20.1).

### Alur

```
[Stg Cmt]  →  N-ACTION-RQ (berisi SOP Instance UID file yang dikirim)
     ↓
   PACS nerima, proses async
     ↓
   PACS kirim N-EVENT-REPORT-RQ ke SCP listener kita
     ↓
   Log: "StgCmt Success: X instances" atau "StgCmt Failed: X instances"
```

### Langkah

1. Kirim file via [Send to PACS]
2. Complete MPPS (kalau lagi workflow)
3. Pastikan SCP **sedang berjalan** (Start Server)
4. Klik **[Stg Cmt]**
5. Tunggu event report dari PACS (bisa beberapa detik)

### Kalau Gagal

```
StgCmt N-ACTION: 0x0110
```
→ Service Storage Commitment di PACS belum siap. Cek log PACS.

Tidak ada event report masuk:
→ PACS tidak bisa mengirim N-EVENT-REPORT karena:
  - SCP listener tidak aktif
  - AE SCP tidak dikenal
  - Firewall blokir port SCP

### Catatan

- Storage Commitment bersifat **asynchronous** — PACS kirim balasan kapan saja
- SCP harus aktif di port yang bisa diakses PACS
- Kalau PACS di Docker dan simulator di host, pastikan port SCP (11113) bisa diakses dari container

---

## C-MOVE

**Apa yang terjadi:** Ambil seluruh study dari PACS. PACS akan mengirim semua instance study ke SCP yang kita tunjuk.

Protokol: `C-MOVE-RQ` pada SOP Class `StudyRootQueryRetrieveInformationModelMove` (1.2.840.10008.5.1.4.1.2.2.2).

### Langkah

1. Start SCP dulu (panel Storage SCP → [Start Server])
2. Pilih study dari worklist
3. Klik **[Retrieve Study]**
4. PACS kirim file ke SCP tujuan (default: `SIMULATOR-SCP` port `11113`)
5. File tersimpan di folder storage SCP (default: `~/dicom-received/`)

### Kalau Gagal

```
C-MOVE: 0xA801
```
**Artinya:** Move Destination Unknown — PACS tidak kenal AE tujuan (`SIMULATOR-SCP`).

**Solusi:** Daftarkan AE `SIMULATOR-SCP` di konfigurasi PACS (lihat Panduan Integrasi).

### Catatan

- C-MOVE butuh SCP aktif — pastikan [Start Server] sudah diklik
- Jumlah file yang diterima tergantung isi study
- File tersimpan di `<storage_dir>/<StudyInstanceUID>/<SOPInstanceUID>.dcm`

---

## Storage SCP

**Apa yang terjadi:** Aplikasi jadi server DICOM. Bisa terima file dari PACS atau modality lain. Berguna untuk:
- Menerima hasil C-MOVE
- Menjadi tujuan Storage Commitment
- Testing kirim dari aplikasi DICOM lain

### Detail

| Aspek | Detail |
|-------|--------|
| AE Title | SIMULATOR-SCP (bisa diganti) |
| Port | 11113 (bisa diganti) |
| Support | Semua Storage SOP Class |
| Listener | Juga handle N-EVENT-REPORT untuk Storage Commitment |
| Penyimpanan | `~/dicom-received/<StudyUID>/<SOPUID>.dcm` |

### Langkah

1. Isi field:
   - **AE Title**: `SIMULATOR-SCP`
   - **Listen Port**: `11113`
   - **Storage Dir**: `~/dicom-received`
2. Klik **[Start Server]**
3. Status: **● Listening on :11113**
4. Kirim file dari PACS / aplikasi lain
5. File masuk → log:
   ```
   Received: BUDI^UTOMO (BUDI001) [CT]
     SOP: 1.2.3.4.5.6.7.8.9
     Study: 1.2.3.4.5.6.7.8.10
     Saved: /home/user/dicom-received/1.2.3.4.../1.2.3.4....dcm
   ```
6. Klik **[Stop Server]** untuk menghentikan

### Catatan Penting

- SCP jalan di **thread terpisah** — GUI tetap responsif
- SCP otomatis berhenti saat aplikasi ditutup
- Kalau port sudah dipakai, start akan gagal — ganti port lain
- Untuk integrasi dengan PACS, daftarkan AE SCP di konfigurasi PACS

---

## Dataset Viewer

**Apa yang terjadi:** Menampilkan isi (dump) dataset DICOM yang sedang dimuat. Berguna untuk debugging — melihat tag apa saja yang ada di file sebelum dikirim.

### Langkah

1. Load file (Browse DICOM atau From Image)
2. Klik **[Dump Dataset]**
3. Output di panel log:

```
── DICOM Dataset ──
  PatientName: BUDI^UTOMO
  PatientID: BUDI001
  StudyInstanceUID: 1.2.826.0.1...
  SeriesInstanceUID: 1.2.826.0.1...
  SOPInstanceUID: 1.2.826.0.1...
  Modality: CT
  StudyDescription: Test
  ...
```

Dataset yang ditampilkan adalah hasil **merge** — kalau ada pasien dipilih, data pasien dari worklist sudah dimasukkan.

---

## Cancel

Tombol Cancel tersedia di beberapa tempat untuk membatalkan operasi yang sedang berjalan.

### Cara Kerja

1. Klik **[Cancel]** → `threading.Event.set()` dipanggil
2. Thread yang sedang berjalan ngecek flag `is_set()`:
   - Sebelum memulai operasi → langsung return
   - Sedang di tengah blocking call → `assoc.abort()` dipanggil
3. Tombol kembali normal

### Di Mana Saja?

| Panel | Tombol | Membatalkan |
|-------|--------|-------------|
| PACS Connection | [Cancel] | Test Connection (C-ECHO) |
| Worklist | [Cancel] | Refresh (C-FIND) |

### Catatan

- Cancel tidak bisa membatalkan operasi yang **sudah** dikirim ke PACS (misal C-STORE yang sudah sampai)
- Tapi bisa membatalkan koneksi yang **sedang** berlangsung
- Kalau operasi sudah selesai 99%, cancel mungkin tidak berefek

---

## Graceful Close

**Apa yang terjadi:** Saat menutup aplikasi (klik X), semua proses dihentikan dengan rapi — tidak ada thread yang menggantung.

### Urutan Close

```
1. Klik X (pojok kanan atas)
       ↓
2. _cancel_event.set()  → semua thread tau harus stop
       ↓
3. scp_frame.destroy()  → SCP shutdown
       ↓
4. update_idletasks()   → GUI refresh (biar ga hang)
       ↓
5. destroy()            → jendela nutup
```

### Catatan

- Thread yang sedang jalan (daemon) akan mati sendiri saat proses exit
- Tidak ada data yang hilang — file yang sudah terkirim tetap aman di PACS
- Kalau ada operasi yang masih jalan, dia akan selesai di background lalu proses exit
