# Belajar DICOM — Studi Kasus Project Ini (PZDR → Orthanc)

Dokumen ini menjelaskan protokol DICOM **menggunakan kasus nyata yang sudah berjalan
di lab ini** — bukan teori umum. Semua contoh memakai stack yang live:

- **Orthanc** (PACS): `localhost:4242` (DICOM), `localhost:8042` (REST/DICOMweb), AE `ORTHANC`
- **OHIF** Viewer: `localhost:3000`
- **PostgreSQL**: index/metadata studi
- **Sample DICOM** nyata: `sample/DX0000005 tes lagi/.../DX000000.dcm`
- **PZDR**: belum dipasang — dan itu memang disengaja (lihat bagian akhir)

> Tujuan: sebelum PZDR masuk, pahami dulu "bahasa" yang sama yang dipakai semua
> perangkat DICOM. Nanti konfigurasi PZDR tinggal mengisi kolom, Anda sudah tahu artinya.

---

## 1. Besar Panggung: Apa yang Sudah Jalan

```
+--------------------------------------------------+
|              Browser (OHIF :3000)                 |
|                                                  |
|   OHIF  ──DICOMweb (HTTP)──►  QIDO/WADO          |
+------------------------▲-------------------------+
                         │
+------------------------┼-------------------------+
|                   Orthanc :8042                   |
|   C-ECHO (verify) · C-STORE (simpan) · REST       |
+------------------------▲-------------------------+
                         │  DICOM protocol (port 4242)
+------------------------┼-------------------------+
|   DCMTK / PZDR (SCU)                              |
+--------------------------------------------------+
```

Dipisahkan dari kasus ini, **4 layer belakang sudah terbukti benar**:
Orthanc → database → DICOMweb → viewer. Jadi nanti kalau PZDR gagal kirim,
area yang perlu dicek tinggal **PZDR → Network → Association** — bukan seluruh sistem.

---

## 2. Konsep Inti yang Pragmatis

| Istilah | Arti sederhana | Contoh di lab |
|---|---|---|
| **SCU** | yang *meminta* layanan / client | DCMTK, nanti PZDR |
| **SCP** | yang *menyediakan* layanan / server | Orthanc |
| **AE Title** | nama aplikasi DICOM (≤16 char) | SCU=`PZDR_DR1`, SCP=`ORTHANC` |
| **IP + Port** | alamat jaringan | `localhost:4242` |
| **Association** | sesi/percakapan yang dinegosiasikan dulu | 8 langkah, lihat §4 |
| **Presentation Context** | sepakatan "saya bisa baca format ini" | Abstract + Transfer Syntax |
| **Abstract Syntax** | *jenis* data (SOP Class) | `DigitalXRayImageStorage` |
| **Transfer Syntax** | *cara enkode* data | `Explicit VR Little Endian` |
| **SOP Class / SOP Instance** | jenis operasi / satu objek konkret | lihat §5 |
| **C-ECHO** | tes koneksi ("halo?") | `echoscu` |
| **C-STORE** | kirim objek ke SCP | `storescu` |
| **UID** | pengenal global unik | `1.2.276.0.7230010.3.1...` |

> AE Title dan IP/port itu **bukan** sekedar "isian kolom" — bersama-sama mereka
> menentukan identitas Association. Kesalahan satu huruf AE = ditolak.

---

## 3. Tools yang Dipakai di Lab Ini

### 3.1 DCMTK (baris perintah, `dcmtk 3.7.0`)

| Tool | Fungsi | Contoh |
|---|---|---|
| `echoscu` | C-ECHO — uji koneksi SCU→SCP | `echoscu -aec ORTHANC -aet TEST localhost 4242` |
| `storescu` | C-STORE — kirim file DICOM | `storescu -aec ORTHANC -aet PZDR_DR1 localhost 4242 file.dcm` |
| `findscu` | C-FIND — query PACS | `findscu -aec ORTHANC -aet TEST localhost 4242 ...` |
| `movescu` | C-MOVE — minta SCP mengirim | `movescu -aec ORTHANC -aet TEST localhost 4242 ...` |
| `dcmdump` | Tampilkan/membedah isi file DICOM | `dcmdump file.dcm` |
| `dcmodify` | Ubah tag dalam file | `dcmodify -m "(0010,0010)=New^Name" file.dcm` |
| `dcmftest` | Cek apakah file itu DICOM valid | `dcmftest file.dcm` |

Semua berperan sebagai **SCU berpura-pura jadi PZDR** — jadi kita bisa uji PACS
tanpa menyentuh mesin DR.

### 3.2 Python / pydicom (`pydicom 3.0.2`)

Untuk membaca, memodifikasi, dan menganalisis DICOM secara terprogram (analisis
riset, anonymize, validasi). Contoh:

```python
import pydicom
ds = pydicom.dcmread("file.dcm", stop_before_pixels=True)  # baca metadata cepat
print(ds.PatientName, ds.Modality, ds.StudyInstanceUID)
```

### 3.2b pynetdicom (`3.0.4`, di venv `/tmp/dicomlab`)

Untuk menjadi **SCU python** dan melihat PDU asosiasi di level protokol tanpa root.
Script siap pakai: **`scu_demo.py`** (lihat §4b).

### 3.3 Network

- `nc -zv <host> <port>` — cek port terbuka (sesuai dokumen ACCESS-JARINGAN).
- `tcpdump` / `wireshark` — **belum terpasang**. Untuk melihat PDU di level wire
  (A-ASSOCIATE-RQ/AC dst), pasang salah satu. Lakukan *nanti* — §4 memakai
  output verbose DCMTK yang sudah cukup dulu.

---

## 4. Tahap 2 — Apa yang Terjadi "di Balik Layar" C-STORE

`storescu -v` menunjukkan percakapan. Yang **bukan** "copy file" melainkan:

```
1. TCP Connect
2. A-ASSOCIATE-RQ   →  SCU bernama PZDR_DR1 minta bicara dgn ORTHANC,
                       mengajukan "saya bisa baca format A, B, C"
3. A-ASSOCIATE-AC   →  SCP memilih format yang disetujui (Presentation Context)
4. C-STORE-RQ       →  SCU mengirim objek (Message 1, DX)
5. C-STORE-RSP      →  SCP menjawab sukses (Status: Success)
6. A-RELEASE-RQ / RP→  tutup sesi dengan sopan
```

Output asli dari lab (di-ringkas):

```
I: Requesting Association
D: Proposing Presentation Context
D:   Abstract Syntax: DigitalXRayImageStorageForPresentation
D:   Proposed Transfer Syntax(es): ...Explicit/Implicit VR...
D:   Accepted SCP/SCU Role: Default
I: Association Accepted (Max Send PDV: 16372)
I: Sending Store Request (MsgID 1, DX)
I: Received Store Response (Success)
I: Releasing Association
```

Lihat: ada **negosiasi (presentation context)** sebelum data dikirim. Tanpa
sepakat format — transfer syntax — tidak ada transfer. Ini alasan "association OK
tapi C-STORE gagal" sering terjadi karena format tidak cocok (lihat TROUBLESHOOTING).

Kelas penting: `storescu -d` menampilkan **seluruh** Abstract Syntax yang diajukan
(lusinan SOP Class). PACS hanya menerima yang relevan (mis. Digital X-Ray).

---

## 4b. Melihat PDU di Level Wire (tanpa root — pynetdicom)

`tcpdump`/`wireshark` butuh root, tapi **pynetdicom** (python) bisa menampilkan
struktur PDU yang sama persis — cukup set logging DEBUG. Sudah disiapkan sebagai
script: **`scu_demo.py`** di root proyek.

### Setup sekali (venv lokal, bukan global)

```bash
python3 -m venv /tmp/dicomlab
/tmp/dicomlab/bin/pip install pynetdicom
```

### Jalankan

```bash
# C-ECHO saja:
/tmp/dicomlab/bin/python scu_demo.py

# C-ECHO + C-STORE file sample:
/tmp/dicomlab/bin/python scu_demo.py \
  "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"
```

### Output yang ditampilkan (aksi vs diagram)

```
======================= OUTGOING A-ASSOCIATE-RQ PDU ========================
Presentation Context:
  Context ID: 1 (Proposed)
    Abstract Syntax: =Verification SOP Class
    Proposed Transfer Syntaxes: [...]
========================== END A-ASSOCIATE-RQ PDU ==========================

======================= INCOMING A-ASSOCIATE-AC PDU ========================
Presentation Context:
  Context ID: 1 (Accepted)
    Abstract Syntax: =Verification SOP Class
    Accepted SCP/SCU Role: Default
    Accepted Transfer Syntax: =Explicit VR Little Endian
========================== END A-ASSOCIATE-AC PDU ==========================

======================= OUTGOING DIMSE MESSAGE =============================   ← C-ECHO-RQ
======================= INCOMING DIMSE MESSAGE =============================   ← C-ECHO-RSP (0x0000)
```

Korespondensi dengan diagram §4:

| Diagram | Terlihat di output |
|---|---|
| TCP Connect | (implisit) |
| A-ASSOCIATE-RQ | `OUTGOING A-ASSOCIATE-RQ PDU` |
| A-ASSOCIATE-AC | `INCOMING A-ASSOCIATE-AC PDU` |
| C-STORE-RQ / RSP | `OUTGOING/INCOMING DIMSE MESSAGE` |

Simpan di venv: `/tmp/dicomlab` (belum masuk git — hanya alat belajar).

---

## 5. Tahap 3 — Membedah File DICOM (satu file, gambar + identitas)

Menggunakan sample NYATA: `sample/DX0000005 tes lagi/.../DX000000.dcm` (±18 MB).

### 5.1 Struktur file (dua bagian)

```
┌─ File Meta Header (group 0002)  →  info cara membaca file
└─ Data Set (group 0008+)          →  data pasien + gambar
```

`dcmdump` memisahkan keduanya dengan baris `# Dicom-Meta-Information-Header`.

### 5.2 File Meta Header — cara file di-encode

```
(0002,0000) UL 200                              Panjang metadata
(0002,0002) UI =DigitalXRayImageStorageForPresentation   jenis objek (SOP Class)
(0002,0003) UI [1.2.276.0.7230010.3.1.4...288]  SOPInstanceUID = identitas unik file ini
(0002,0010) UI =LittleEndianExplicit            Transfer Syntax = aturan enkode byte
(0002,0012) UI [1.2.276.0.7230010.3.0.3.6.6]    ImplementationClassUID (vendor DCMTK)
(0002,0013) SH [OFFIS_DCMTK_366]                Implementasi yang menghasilkan file
```

> `SOPInstanceUID` di Meta Header **harus sama** dengan `(0008,0018)` di Data Set —
> kalau beda, file korup/invalid.

### 5.3 Data Set — identitas pasien & studi

Tag-tag kunci (penting untuk matching di RIS/PACS):

| Tag (attribute) | Contoh nilai | Mengapa penting |
|---|---|---|
| `(0010,0010)` PatientName | `tes lagi` | identitas pasien |
| `(0020,000D)` StudyInstanceUID | `...1.2.5.20250903202455` | kesatuan satu pemeriksaan |
| `(0020,000E)` SeriesInstanceUID | `...5.20250903202455.0` | satu seri (mis. posisi Chest PA) |
| `(0008,0018)` SOPInstanceUID | `...6284.1777266578.288` | satu gambar/objek |
| `(0008,0050)` AccessionNumber | `DX0000005` | dipakai RIS utk match ke order |
| `(0008,0060)` Modality | `DX` | jenis modalitas |
| `(0008,1030)/(103e)` Study/SeriesDescription | `Chest PA` | deskripsi |
| `(0008,0020)` StudyDate | `20250903` | tanggal |

Hierarki: **Patient → Study → Series → Instance (SOP)**. Tiga UID inilah yang
membangun pohon data yang Anda lihat di OHIF/Orthanc (`/studies`, `/series`,
`/instances`).

### 5.4 Pixel Data

`(7fe0,0010)` = PixelData (di ujung file). Itulah gambar sebenarnya. Karena file ini
*uncompressed* (Explicit VR LE), datanya mentah (±18 MB). Settings seperti:
`(0028,0010)` Rows, `(0028,0011)` Columns, `(0028,0100)` BitsAllocated, `(0028,0004)`
PhotometricInterpretation — menentukan cara membaca byte itu jadi gambar.

### 5.4b Pixel Data → Gambar PNG (latihan Tahap 3)

Script: **`dcm_to_png.py`** — membaca PixelData, menerapkan window/level, dan
menghasilkan PNG. Menunjukkan "gambar" yang tersembunyi di balik byte mentah.

```bash
# C-ECHO saja:
/tmp/dicomlab/bin/python dcm_to_png.py \
  "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"
```

Data sample yang diproses (output asli):

```
Modality        : DX
Rows x Columns  : 3072 x 3072
BitsAllocated   : 16   BitsStored: 16
SamplesPerPixel : 1
Photometric     : MONOCHROME1
PixelRepr       : 0
Rescale         : slope=1 intercept=0
Window center/width: 7180 / 14310
PixelData shape : (3072, 3072), dtype=uint16, min=160, max=19977
PNG tersimpan: sample/.../DX000000.png
```

Pelajaran dari angka-angka itu:

| Nilai | Artinya |
|---|---|
| `3072 x 3072, 16-bit` | gambar besar, nilai mentah 0–65535 (dtype uint16) |
| `MONOCHROME1` | terang = nilai kecil; **perlu invert** saat display |
| `WindowCenter 7180 / Width 14310` | rentang nilai yang "enak dilihat": 7180±7155 |
| `Rescale slope=1 intercept=0` | nilai mentah = nilai display (tidak ada transformasi) |
| min 160 / max 19977 | kontras nyata jauh di bawah 65535 → window/level wajib |

Alur script: `pixel_array` (numpy) → clamp ke window (center±width/2) →
normalisasi 0–255 → invert (MONOCHROME1) → simpan PNG 8-bit.
Tanpa window/level gambar akan nyaris hitam/putih total.

> PNG hasil generate tidak di-git (artefak, bisa besar). Script yang di-git.

### 5.5 Bukti nyata matching di lab

File sample ini (**PatientName `tes lagi`, StudyInstanceUID `...02455`**) sudah
dikirim via `storescu` dan tampil sebagai 1 study di `curl localhost:8042/studies`.
Artinya: PACS meng-indeks ketiga UID itu ke PostgreSQL — dan OHIF bisa menampilkan
gambarnya karena nilai Rows/Columns/PixelData benar.

---

## 6. Peran PZDR Nanti

PZDR hanya menjadi **SCU lain** yang bicara bahasa yang sama seperti `storescu` di atas:

| Aspek | storescu (lab) | PZDR (nanti) |
|---|---|---|
| SCU AE Title | `PZDR_DR1` | `PZDR_DR1` (isi 4.6.4 Host AETitle) |
| SCP AE Title | `ORTHANC` | `ORTHANC` (4.6.4 AETitle) |
| IP:Port SCP | `localhost:4242` | `10.205.136.1:4242` (Hostname) |
| Operasi | C-ECHO + C-STORE | C-ECHO (Tombol Test) + C-STORE (upload) |

Karena `echoscu` dan `storescu` sudah sukses ke Orthanc, konfigurasi PZDR tinggal
menyamakan 4 nilai itu. **Kalau gagal**, bukan Orthanc yang salah melainkan
PZDR → Network → Association (cek `docs/TROUBLESHOOTING.md`).

---

## 7. Roadmap Belajar

| Tahap | Status | Isi |
|---|---|---|
| 1. Foundation (Docker, Orthanc, OHIF, DICOMweb, C-ECHO, C-STORE) | ✅ | sudah dibangun & diverifikasi |
| 2. Protokol (Association, Presentation Context, Transfer Syntax) | ✅ | §4 (storescu `-d`) + §4b (pynetdicom PDU) |
| 3. Membedah file DICOM | 🟡 | §5 — dcmdump + pydicom |
| 4. PZDR | ⏳ | tinggal isi 4 nilai SCU (docs/INSTALL-PZDR.md) |
| 5. Workflow RS (SIMRS→RIS→MWL→PZDR→Orthanc→OHIF→laporan) | ⏳ | integrasi MWL nanti |

---

## Latihan yang Bisa Dilakukan Sendiri

```bash
# 1. Buka file sample lalu cek benar-benar DICOM:
dcmftest "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"

# 2. Membedah metadata pasien & UID:
dcmdump "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm" \
  | grep -E "PatientName|StudyInstanceUID|PixelData"

# 3. Kirim ulang ke Orthanc, perhatikan negosiasi:
storescu -d -aec ORTHANC -aet PZDR_DR1 localhost 4242 \
  "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"

# 4. Cek studi masuk di REST:
curl -s http://localhost:8042/studies

# 5. (Python) baca cepat tanpa pixel data:
python3 -c "
import pydicom
ds = pydicom.dcmread('sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm', stop_before_pixels=True)
print(ds.PatientName, '|', ds.Modality, '|', ds.StudyInstanceUID)
"

# 6. (pynetdicom) lihat PDU asosiasi di level protokol:
/tmp/dicomlab/bin/python scu_demo.py \
  "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"

# 7. (pixel→PNG) bedah pixel data & simpan gambar:
/tmp/dicomlab/bin/python dcm_to_png.py \
  "sample/DX0000005 tes lagi/DX0000005 Chest PA/DX Chest PA/DX000000.dcm"
```

---

## Referensi

- File konfigurasi: `orthanc/orthanc.json`, `docker-compose.yml`
- Sample DICOM: `sample/`
- Panduan lain: `docs/INSTALL-PZDR.md`, `docs/TROUBLESHOOTING.md`, `docs/ACCESS-JARINGAN.md`