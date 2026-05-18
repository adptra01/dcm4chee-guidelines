# Panduan Penggunaan DCM4CHEE Archive 5.x
## Dari Kasus Sederhana hingga Kompleks — Step by Step

---

## Daftar Isi

### Bagian 1: Operasi Dasar
- [Case 1: Start & Stop Sistem](#case-1-start--stop-sistem)
- [Case 2: Cek Status & Kesehatan Container](#case-2-cek-status--kesehatan-container)
- [Case 3: Lihat Log & Debug Real-time](#case-3-lihat-log--debug-real-time)
- [Case 4: Akses Web UI & Navigasi Halaman](#case-4-akses-web-ui--navigasi-halaman)

### Bagian 2: DICOM & Konektivitas
- [Case 5: C-ECHO — Ping DICOM ke Archive](#case-5-c-echo--ping-dicom-ke-archive)
- [Case 6: Daftarkan AE Title Modality Baru](#case-6-daftarkan-ae-title-modality-baru)
- [Case 7: Upload Gambar DICOM via C-STORE](#case-7-upload-gambar-dicom-via-c-store)
- [Case 8: Upload Gambar DICOM via STOW-RS (REST API)](#case-8-upload-gambar-dicom-via-stow-rs-rest-api)
- [Case 9: Cari Study Pasien via QIDO-RS](#case-9-cari-study-pasien-via-qido-rs)
- [Case 10: Download & Lihat Gambar via WADO-RS](#case-10-download--lihat-gambar-via-wado-rs)

### Bagian 3: Integrasi Sistem
- [Case 11: Setup HL7 Receiver & Modality Worklist](#case-11-setup-hl7-receiver--modality-worklist)
- [Case 12: Kirim Order Radiologi via HL7 ORM](#case-12-kirim-order-radiologi-via-hl7-orm)
- [Case 13: Kirim Data Pasien via HL7 ADT](#case-13-kirim-data-pasien-via-hl7-adt)
- [Case 14: Retrieve Gambar via DICOM C-MOVE](#case-14-retrieve-gambar-via-dicom-c-move)

### Bagian 4: Manajemen & Perawatan
- [Case 15: Backup Database PostgreSQL](#case-15-backup-database-postgresql)
- [Case 16: Restore Database dari Backup](#case-16-restore-database-dari-backup)
- [Case 17: Monitor Storage & Performa](#case-17-monitor-storage--performa)
- [Case 18: Reset Password Web UI yang Lupa](#case-18-reset-password-web-ui-yang-lupa)

### Bagian 5: Troubleshooting Lanjutan
- [Case 19: Debug Error 500 di Web UI](#case-19-debug-error-500-di-web-ui)
- [Case 20: Container Crash Loop — Diagnosis & Solusi](#case-20-container-crash-loop--diagnosis--solusi)

---

## Bagian 1: Operasi Dasar

---

### Case 1: Start & Stop Sistem

**Tujuan:** Menghidupkan dan mematikan semua container DCM4CHEE.

**Alat:** Docker Compose atau Podman Compose.

#### Langkah-Langkah

**1a. Start semua container (Docker)**

```bash
cd ~/dcm4chee-training

docker compose -f docker-compose.level1-basic.yml up -d
```

| Flag | Arti |
|------|------|
| `-f` | Tentukan file compose yang dipakai |
| `up` | Buat dan start container |
| `-d` | Detached mode (jalan di background) |

**1b. Start semua container (Podman)**

```bash
cd ~/dcm4chee-training

podman compose -f podman-compose.level1.yml up -d

# Atau pakai script
./podman-up.sh
```

**1c. Stop semua container**

```bash
# Docker
docker compose -f docker-compose.level1-basic.yml down

# Podman
podman compose -f podman-compose.level1.yml down
# Atau
./podman-down.sh
```

**1d. Stop + hapus semua data (reset total)**

```bash
# Semua volume ikut terhapus — data hilang permanen!
docker compose -f docker-compose.level1-basic.yml down -v
```

---

#### ✅ Output Sukses

```
[+] Running 3/3
 ✔ Container dcm4chee-ldap   Started
 ✔ Container dcm4chee-db     Started
 ✔ Container dcm4chee-arc    Started
```

Setelah 3-10 menit, semua container akan berstatus `healthy`.

#### ❌ Kendala & Solusi

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `port is already allocated` | Port 8080/5432/389 dipakai service lain | `sudo ss -tlnp \| grep -E '8080\|5432\|389'`, stop service tersebut |
| `no matching manifest for linux/arm64` | Arsitektur tidak cocok | Gunakan image versi arm64 jika tersedia, atau emulasi via QEMU |
| `Cannot connect to the Docker daemon` | Docker daemon tidak jalan | `sudo systemctl start docker` |
| `short-name "dcm4che" did not resolve` | Podman tanpa prefix registry | Gunakan `docker.io/dcm4che/...` |

---

### Case 2: Cek Status & Kesehatan Container

**Tujuan:** Memastikan semua container berjalan dengan benar.

**Alat:** Docker CLI, Podman CLI, atau cURL.

#### Langkah-Langkah

**2a. Cek status dasar**

```bash
# Docker
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Podman
podman ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

**2b. Cek health check detail**

```bash
# Cek satu per satu
docker inspect dcm4chee-ldap --format '{{.State.Health.Status}}'
docker inspect dcm4chee-db --format '{{.State.Health.Status}}'
docker inspect dcm4chee-arc --format '{{.State.Health.Status}}'

# Cek semua sekaligus
for c in ldap db arc; do
  status=$(docker inspect dcm4chee-$c --format '{{.State.Health.Status}}' 2>/dev/null)
  echo "dcm4chee-$c: $status"
done
```

**2c. Cek port mendengar**

```bash
# Pastikan semua port yang dibutuhkan terbuka
for port in 389 5432 8080 11112; do
  if ss -tlnp | grep -q ":$port "; then
    echo "[OK] Port $port — listening"
  else
    echo "[--] Port $port — NOT listening"
  fi
done
```

**2d. Cek Web UI**

```bash
# Cek HTTP response code
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/dcm4chee-arc/ui2/
```

---

#### ✅ Output Sukses

```
NAMES            STATUS                    PORTS
dcm4chee-ldap    Up About an hour (healthy) 0.0.0.0:389->389/tcp
dcm4chee-db      Up About an hour (healthy) 0.0.0.0:5432->5432/tcp
dcm4chee-arc     Up About an hour (healthy) 0.0.0.0:8080->8080/tcp, 0.0.0.0:11112->11112/tcp

HTTP 200
```

Semua container harus `healthy`, bukan `starting` atau `unhealthy`.

#### ❌ Kendala & Solusi

| Status | Arti | Solusi |
|--------|------|--------|
| `unhealthy` | Health check gagal terus | Lihat logs: `docker logs dcm4chee-ldap --tail 20` |
| `starting` | Masih dalam proses startup | Tunggu 3-10 menit, terutama first run |
| `exited` | Container mati sendiri | `docker logs dcm4chee-arc --tail 50` untuk lihat penyebab |
| HTTP `502` | Archive belum selesai deploy | Tunggu, cek `docker logs dcm4chee-arc` untuk "WFLYSRV0025" |
| HTTP `000` / `Connection refused` | Container belum jalan | Cek `docker ps`, pastikan container running |

---

### Case 3: Lihat Log & Debug Real-time

**Tujuan:** Memonitor aktivitas dan mendiagnosis masalah dari log container.

**Alat:** Docker CLI atau Podman CLI.

#### Langkah-Langkah

**3a. Lihat log semua service**

```bash
# Semua service
docker compose -f docker-compose.level1-basic.yml logs -f

# Hanya archive (paling penting)
docker logs -f dcm4chee-arc
```

**3b. Filter log berdasarkan waktu**

```bash
# 5 menit terakhir
docker logs dcm4chee-arc --since 5m

# 1 jam terakhir
docker logs dcm4chee-arc --since 1h

# Tanggal spesifik
docker logs dcm4chee-arc --since "2025-01-15T10:00:00" --until "2025-01-15T12:00:00"
```

**3c. Filter log berdasarkan level**

```bash
# Cari error
docker logs dcm4chee-arc 2>&1 | grep -i "ERROR\|SEVERE"

# Cari warning
docker logs dcm4chee-arc 2>&1 | grep -i "WARN"

# Cari tanda WildFly sudah siap
docker logs dcm4chee-arc 2>&1 | grep "WFLYSRV0025"
```

**3d. Lihat jumlah baris tertentu**

```bash
# 50 baris terakhir
docker logs dcm4chee-arc --tail 50

# 200 baris terakhir dan ikuti实时
docker logs dcm4chee-arc --tail 200 -f
```

---

#### ✅ Output Sukses (WildFly siap)

```
2025-01-15 10:30:45,123 INFO  [org.jboss.as] (ServerService Thread Pool -- 64)
  WFLYSRV0025: WildFly 34.0.0.Final (WildFly Core 22.0.0.Final) started
```

Tanda archive sudah siap menerima koneksi.

#### ❌ Kendala & Solusi

| Log Error | Arti | Solusi |
|-----------|------|--------|
| `Connection refused (LDAP)` | Archive start sebelum LDAP siap | `docker restart dcm4chee-arc` setelah LDAP healthy |
| `PSQLException: Connection refused` | DB belum siap | Pastikan `POSTGRES_PASSWORD` match, `docker restart dcm4chee-arc` |
| `OutOfMemoryError` | Heap tidak cukup | Naikkan `WILDFLY_JAVA_OPTS` (`-Xms2g -Xmx4g`) |
| `No space left on device` | Storage penuh | Hapus file lama atau tambah storage |
| `ERROR LDAP ... invalid credentials` | Password LDAP mismatch | Pastikan `LDAP_PASSWORD` sama di semua service |

---

### Case 4: Akses Web UI & Navigasi Halaman

**Tujuan:** Login ke Web UI DCM4CHEE dan memahami halaman-halaman penting.

**Alat:** Web browser.

#### Langkah-Langkah

**4a. Buka Web UI**

```
URL: http://localhost:8080/dcm4chee-arc/ui2
User: root
Pass: secret
```

**4b. Halaman-halaman utama**

| Menu | Ikone | Fungsi |
|------|-------|--------|
| **Patients** | 🧑 | Cari, lihat, edit data pasien |
| **Studies** | 📋 | Cari, lihat, download study |
| **Queue** | ⏳ | Monitor antrian processing / MWL |
| **Monitor** | 📊 | Dashboard monitoring performa |
| **Configuration** | ⚙️ | Semua konfigurasi sistem |
| **Admin** | 🔐 | User management, audit log |

**4c. Konfigurasi dasar lewat Web UI**

Buka **Configuration → Devices → dcm4chee-arc** untuk melihat/mengubah:
- AE Title & port DICOM
- Storage group
- HL7 services
- MWL / MPPS
- Web Applications (STOW-RS, QIDO-RS, WADO-RS)

**4d. Cek storage via Web UI**

**Configuration → Devices → dcm4chee-arc → Storage** — lihat kapasitas terpakai.

---

#### ✅ Output Sukses

Halaman login tampil di browser. Setelah login, dashboard pasien/study muncul.

#### ❌ Kendala & Solusi

| Masalah | Penyebab | Solusi |
|---------|----------|--------|
| Halaman tidak bisa diakses | Container belum siap | Cek `docker ps`, pastikan status `healthy` |
| Login gagal `Invalid Credentials` | Password salah | Default: `root` / `secret` (lihat `.env`) |
| Halaman putih/Loading lama | WildFly masih deploy | Tunggu 3-10 menit, refresh browser |
| Error 403 Forbidden | User tidak punya role admin | Login sebagai `root` punya semua role |

---

## Bagian 2: DICOM & Konektivitas

---

### Case 5: C-ECHO — Ping DICOM ke Archive

**Tujuan:** Memverifikasi koneksi DICOM antara modality/client dengan archive.

**Alat:** `echoscu` (dcm4che toolkit) atau DICOM viewer.

#### Langkah-Langkah

**5a. C-ECHO via command line**

```bash
# Format:
# echoscu <host> <port> -aet <calling_ae> -aec <called_ae>

echoscu localhost 11112 -aet TEST_CLIENT -aec DCM4CHEE
```

| Parameter | Arti | Contoh |
|-----------|------|--------|
| `localhost` | Host archive | IP server PACS |
| `11112` | DICOM port | Port DICOM archive |
| `-aet` | Calling AE Title (client) | `CT_SIEMENS` |
| `-aec` | Called AE Title (tujuan) | `DCM4CHEE` |

**5b. C-ECHO via Web UI**

Configuration → Devices → dcm4chee-arc → Network → Network AE
→ Pilih salah satu AE → klik **C-ECHO** button.

---

#### ✅ Output Sukses

```
I: Requesting Association
I: Association accepted (max send: 131072)
I: Received C-ECHO RQ
I: Sending C-ECHO RSP
I: Releasing Association
Result: 0x0000 (Success)
```

#### ❌ Kendala & Solusi

| Error Output | Arti | Solusi |
|-------------|------|--------|
| `Association Rejected: called AE title not recognized` | `DCM4CHEE` tidak dikenal | Buka Web UI → Configuration → Devices → dcm4chee-arc → Network → pastikan AE Title `DCM4CHEE` ada |
| `Association Rejected: calling AE title not authorized` | `TEST_CLIENT` tidak dikenal | Tambahkan `TEST_CLIENT` sebagai AE Title di Web UI |
| `Connection refused` | Port 11112 tidak terbuka | Cek `ss -tlnp \| grep 11112`, restart container |
| `Timeout` | Firewall blok atau network | Cek `sudo ufw status`, allow port 11112 |

**Solusi cepat untuk "calling AE title not authorized":**

```bash
# Alternatif: gunakan echoscu dengan AE yang sudah terdaftar
# Default: DCM4CHEE sudah terdaftar sebagai both calling dan called AE
echoscu localhost 11112 -aet DCM4CHEE -aec DCM4CHEE
```

---

### Case 6: Daftarkan AE Title Modality Baru

**Tujuan:** Menambahkan modality baru (CT, MRI, USG) agar bisa terhubung ke archive.

**Alat:** Web UI.

#### Langkah-Langkah

**6a. Tambah AE Title lewat Web UI**

1. Login ke Web UI: `http://localhost:8080/dcm4chee-arc/ui2`
2. Menu: **Configuration → Network → Network AE**
3. Klik tombol **Create** (atau icon **+**)
4. Isi form:

| Field | Contoh Value | Penjelasan |
|-------|-------------|------------|
| **AE Title** | `CT_SIEMENS` | Nama modality, max 16 karakter, huruf besar |
| **Host Name** | `192.168.1.10` | IP address modality di jaringan RS |
| **Port** | `11112` | DICOM port di sisi modality (untuk retrieve) |
| **Description** | `CT Scanner Siemens` | Catatan opsional |

5. Di tab **Transfer Syntaxes**, centang minimal:
   - `1.2.840.10008.1.2` — Implicit VR Little Endian (wajib)
   - `1.2.840.10008.1.2.1` — Explicit VR Little Endian (wajib)
   - `1.2.840.10008.1.2.4.50` — JPEG Baseline (jika modality kompres)

6. Klik **Save**

**6b. Tambahkan ke Other AE Titles di Device**

1. Menu: **Configuration → Devices → dcm4chee-arc**
2. Scroll ke **Network Configuration** → **Other AE Titles**
3. Tambahkan `CT_SIEMENS` di daftar (pisahkan dengan `\` jika lebih dari satu)
4. Contoh: `CT_SIEMENS\MRI_GE\USG_PHILIPS`
5. Klik **Save**

**6c. Verifikasi koneksi**

```bash
# Test C-ECHO dengan AE title yang baru
echoscu localhost 11112 -aet CT_SIEMENS -aec DCM4CHEE
```

---

#### ✅ Output Sukses

Association accepted setelah AE title ditambahkan.

#### ❌ Kendala & Solusi

| Kendala | Penyebab | Solusi |
|---------|----------|--------|
| Modality masih reject meski sudah di-add | Modality configure connection-nya salah | Cek setting DICOM di modality: Called AE = `DCM4CHEE`, Port = `11112`, Host = IP server |
| "AE Title already exists" | Nama sudah dipakai | Gunakan nama unik, misal `CT_SIEMENS_2` |
| Perubahan tidak生效 | Belom klik Save | Pastikan tombol Save berubah jadi hijau |
| C-ECHO ok tapi C-STORE gagal | Transfer Syntax tidak cocok | Cek log: `docker logs dcm4chee-arc \| grep "TransferSyntax"` |

---

### Case 7: Upload Gambar DICOM via C-STORE

**Tujuan:** Mengirim file DICOM dari command line ke archive.

**Alat:** `storescu` (dcm4che toolkit) + file DICOM sample.

#### Langkah-Langkah

**7a. Download sample DICOM file**

```bash
mkdir -p /tmp/dcm-samples
cd /tmp/dcm-samples

# Download sample CT scan
wget -O ct-sample.dcm \
  "https://www.dicomserver.co.uk/SampleDICOMFiles/CT/CT.1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047.dcm"

# atau MRI sample
wget -O mr-sample.dcm \
  "https://www.dicomserver.co.uk/SampleDICOMFiles/MR/MR.1.2.840.113619.2.1.2411.005544122258901902019.0055542589019051.dcm"

ls -la
# total 2000K
# -rw-rw-r-- 1 user user 1012K ct-sample.dcm
# -rw-rw-r-- 1 user user 1024K mr-sample.dcm
```

**7b. Kirim via C-STORE**

```bash
# storescu <host> <port> <file> -aet <calling_ae> -aec <called_ae>

storescu localhost 11112 /tmp/dcm-samples/ct-sample.dcm \
  -aet TEST_CLIENT -aec DCM4CHEE
```

**7c. Kirim multiple files**

```bash
storescu localhost 11112 /tmp/dcm-samples/*.dcm \
  -aet TEST_CLIENT -aec DCM4CHEE
```

---

#### ✅ Output Sukses

```
I: Requesting Association
I: Association accepted
I: Sending file: ct-sample.dcm
I: Received C-STORE RSP (Status: Success)
I: Released Association
Result: 0x0000 (Success)
```

Setelah berhasil, study baru akan muncul di Web UI (menu **Studies**).

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `Called AE title not recognized` | `DCM4CHEE` tidak ada di LDAP | Tambahkan AE title `DCM4CHEE` via Web UI |
| `Calling AE title not authorized` | `TEST_CLIENT` belum terdaftar | Tambahkan `TEST_CLIENT` sebagai AE Title |
| `File is not a valid DICOM file` | File corrupt atau bukan DICOM | `file ct-sample.dcm` — harus keluar "DICOM medical imaging" |
| `Transfer Syntax not supported` | Format kompresi tidak diterima | Archive menerima Implicit/Explicit LE, JPEG |
| `Storage failure: No space left` | Storage penuh | Cek `df -h`, tambah storage |

**Cek apakah file benar DICOM:**

```bash
file ct-sample.dcm
# Output: DICOM medical imaging data
```

---

### Case 8: Upload Gambar DICOM via STOW-RS (REST API)

**Tujuan:** Mengirim file DICOM menggunakan HTTP/REST — lebih mudah dan firewall-friendly.

**Alat:** `curl`.

#### Langkah-Langkah

**8a. Upload satu file DICOM**

```bash
curl -v -X POST \
  --data-binary @/tmp/dcm-samples/ct-sample.dcm \
  -H "Content-Type: application/dicom" \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

| Parameter | Arti |
|-----------|------|
| `--data-binary` | Kirim file binary |
| `-H "Content-Type: application/dicom"` | Beri tahu server ini file DICOM |
| `/aets/DCM4CHEE/rs/studies` | STOW-RS endpoint untuk study |

**8b. Upload dalam format JSON (metadata only)**

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "00100010": {"vr": "PN", "Value": ["SUSANTO^BUDI"]},
    "00100020": {"vr": "LO", "Value": ["P00123"]},
    "00080060": {"vr": "CS", "Value": ["CT"]},
    "00080020": {"vr": "DA", "Value": ["20250115"]}
  }' \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

> **Catatan:** Ini hanya membuat metadata study tanpa gambar. Untuk upload lengkap, kirim file DICOM asli.

**8c. Upload multi-file (bulk)**

```bash
# Gabungkan multiple DICOM files ke satu request
curl -X POST \
  -H "Content-Type: multipart/related; type=application/dicom" \
  -F "file1=@/tmp/dcm-samples/ct-sample.dcm" \
  -F "file2=@/tmp/dcm-samples/mr-sample.dcm" \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

---

#### ✅ Output Sukses

```
< HTTP/1.1 200 OK
< Content-Type: application/dicom+json
<
{
  "0020000D": {
    "vr": "UI",
    "Value": ["1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047"]
  },
  "00080020": {
    "vr": "DA",
    "Value": ["20250115"]
  }
}
```

HTTP 200 berarti upload berhasil. Response berisi Study UID yang baru dibuat.

#### ❌ Kendala & Solusi

| Error HTTP | Arti | Solusi |
|-----------|------|--------|
| `400 Bad Request` | File bukan DICOM valid atau header salah | Cek `Content-Type: application/dicom` |
| `401 Unauthorized` | Butuh autentikasi (Level 3) | Include token Keycloak di header |
| `413 Payload Too Large` | File > max upload size | Naikkan limit di WildFly config |
| `415 Unsupported Media Type` | Content-Type tidak dikenal | Pastikan `application/dicom` bukan `application/dcm` |
| `503 Service Unavailable` | Archive belum siap | Tunggu WildFly selesai deploy |

---

### Case 9: Cari Study Pasien via QIDO-RS

**Tujuan:** Mencari data study/patient menggunakan REST API.

**Alat:** `curl`.

#### Langkah-Langkah

**9a. Cari semua study (tanpa filter)**

```bash
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

**9b. Cari study dengan filter**

```bash
# Berdasarkan Patient ID
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientID=P00123"

# Berdasarkan Nama Pasien
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientName=SUSANTO"

# Berdasarkan Tanggal (format: YYYYMMDD-YYYYMMDD)
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?StudyDate=20250115-20250120"

# Berdasarkan Modalitas
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?Modality=CT"
```

**9c. Cari series dalam study**

```bash
# Ganti <StudyUID> dengan Study UID dari hasil query di atas
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047/series"
```

**9d. Cari instances dalam series**

```bash
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/series/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047/instances"
```

**9e. Gunakan jq untuk format JSON rapi**

```bash
# Install jq dulu
sudo apt install -y jq

# Query dengan format rapi
curl -s "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=5" | jq .
```

---

#### ✅ Output Sukses

```json
[
  {
    "0020000D": {
      "vr": "UI",
      "Value": ["1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047"]
    },
    "00080020": {
      "vr": "DA",
      "Value": ["20250115"]
    },
    "00100010": {
      "vr": "PN",
      "Value": [{"Alphabetic": "SUSANTO^BUDI"}]
    },
    "00100020": {
      "vr": "LO",
      "Value": ["P00123"]
    },
    "00080060": {
      "vr": "CS",
      "Value": ["CT"]
    }
  }
]
```

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `Array kosong []` | Tidak ada hasil | Ubah filter, atau upload study dulu |
| `400 Invalid query` | Parameter tidak dikenal | Cek DICOM tag yang di-query valid |
| `413 Request too long` | Query terlalu kompleks | Tambah `limit=20` |
| `Connection refused` | Archive belum start | Cek status container |

**Tips filter lanjutan:**

```bash
# Fuzzy search (metaphone) — cari nama mirip
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientName=SUSAN&fuzzy=true"

# Include field tertentu
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?includefield=00100010&includefield=00080060"

# Pagination
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=10&offset=20"
```

---

### Case 10: Download & Lihat Gambar via WADO-RS

**Tujuan:** Mengunduh gambar DICOM atau thumbnail dari archive.

**Alat:** `curl`, browser.

#### Langkah-Langkah

**10a. Download satu instance (gambar)**

```bash
# Format: /studies/<StudyUID>/series/<SeriesUID>/instances/<InstanceUID>
# Ambil UID dari hasil QIDO-RS di atas

curl -o /tmp/downloaded.dcm \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047/series/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047/instances/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047"

ls -la /tmp/downloaded.dcm
#-rw-r--r-- 1 user user 1012K downloaded.dcm
```

**10b. Download thumbnail (JPEG preview)**

```bash
curl -o /tmp/preview.jpg \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/wado?requestType=WADO&studyUID=1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047&contentType=image/jpeg"

# Buka file
xdg-open /tmp/preview.jpg
```

**10c. Retrieve study metadata**

```bash
curl -s \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047/metadata" \
  -H "Accept: application/dicom+json" | jq .
```

---

#### ✅ Output Sukses

File DICOM terdownload di `/tmp/downloaded.dcm` dan bisa dibuka dengan DICOM viewer:

```bash
# Buka dengan dcm4che tools
dcmdump /tmp/downloaded.dcm
```

Atau via browser: buka `/tmp/preview.jpg`.

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `404 Not Found` | Study/series/instance tidak ditemukan | Cek UID dari hasil QIDO-RS |
| File 0 bytes | UID salah | Copy paste UID dengan benar |
| `406 Not Acceptable` | Format tidak didukung | Gunakan `Accept: application/dicom` |
| WADO-URI hasil kosong | Parameter salah | `requestType=WADO` + `studyUID=...` wajib ada |

---

## Bagian 3: Integrasi Sistem

---

### Case 11: Setup HL7 Receiver & Modality Worklist

**Tujuan:** Mengaktifkan HL7 service agar archive bisa menerima order dari HIS/RIS.

**Alat:** Web UI.

#### Langkah-Langkah

**11a. Aktifkan HL7 ORM Receiver**

1. Login Web UI → **Configuration → Devices → dcm4chee-arc**
2. Cari **HL7 Service (ORM)** section
3. Set:

| Setting | Value | Penjelasan |
|---------|-------|------------|
| **Enable** | ✅ (centang) | Aktifkan service |
| **Called Application** | `DCM4CHEE` | Nama aplikasi penerima |
| **Port** | `2762` | Port HL7 ORM (default) |
| **Default Modality** | `OT` | Modalitas default jika tidak disebut |
| **Default Station AET** | `DCM4CHEE` | AE title default |

4. Klik **Save**

**11b. Aktifkan HL7 ADT Receiver (untuk data pasien)**

1. Menu: **Configuration → Devices → dcm4chee-arc → HL7 Service (ADT)**
2. Set:

| Setting | Value |
|---------|-------|
| **Enable** | ✅ (centang) |
| **Called Application** | `DCM4CHEE` |
| **Port** | `2575` |
| **Message Types** | `ADT^A01, ADT^A08, ADT^A04, ADT^A05, ADT^A40` |

3. Klik **Save**

**11c. Aktifkan MWL SCP (supaya modality bisa tarik worklist)**

1. Menu: **Configuration → Devices → dcm4chee-arc → MWL SCP**
2. Set:

| Setting | Value |
|---------|-------|
| **Enable** | ✅ (centang) |
| **Called AE Titles** | `DCM4CHEE` |
| **Calling AE Titles** | (biarkan kosong = accept all) |

3. Klik **Save**

**11d. Verifikasi port HL7 terbuka**

```bash
# Cek port 2762 (ORM) dan 2575 (ADT)
ss -tlnp | grep -E '2762|2575'
```

---

#### ✅ Output Sukses

```
LISTEN 0 100 0.0.0.0:2762  0.0.0.0:*   users:(("java",pid=1234,fd=50))
LISTEN 0 100 0.0.0.0:2575  0.0.0.0:*   users:(("java",pid=1234,fd=51))
```

#### ❌ Kendala & Solusi

| Kendala | Penyebab | Solusi |
|---------|----------|--------|
| Port 2762/2575 tidak listening | HL7 service belum di-enable | Cek setting Enable = true |
| Container restart setelah save | WildFly perlu reload | Tunggu 30 detik, service akan aktif |
| "HL7 connection refused" | Firewall block | `sudo ufw allow 2762/tcp && sudo ufw allow 2575/tcp` |

---

### Case 12: Kirim Order Radiologi via HL7 ORM

**Tujuan:** Mengirim order dari HIS/EMR ke Modality Worklist.

**Alat:** `nc` (netcat).

#### Langkah-Langkah

**12a. Kirim HL7 ORM message sederhana**

```bash
# Buat file HL7 message
cat > /tmp/orm-order.hl7 << 'EOF'
MSH|^~\&|SIMRS|RSUD_SEHAT|DCM4CHEE|DCM4CHEE|202501151200||ORM^O01|MSG001|P|2.5
PID|1||P00123^^^DCM4CHEE||SUSANTO^BUDI||19850515|M|||JL.MERDEKA NO.10^^JAKARTA^^^
ORC|NW|ORD001|||SC
OBR|1|ORD001||CT^CT SCAN THORAX|||202501151400|||RAD001|||CT_SIEMENS
EOF

# Kirim via MLLP ke port ORM (2762)
echo -ne "$(cat /tmp/orm-order.hl7)\r\n" | nc -w 5 localhost 2762
```

**Penjelasan setiap segment:**

| Segment | Field | Arti |
|---------|-------|------|
| `MSH` | Message Header: sender=DCM4CHEE, receiver=DCM4CHEE, type=ORM^O01 |
| `PID` | Pasien: ID=P00123, Name=SUSANTO^BUDI, DOB=19850515, Sex=M |
| `ORC` | Order: ID=ORD001, Status=NW (New order) |
| `OBR` | Procedure: CT SCAN THORAX, jadwal=202501151400, modality=CT_SIEMENS |

**12b. Kirim order yang lebih lengkap (dengan Accession Number)**

```bash
cat > /tmp/orm-order2.hl7 << 'EOF'
MSH|^~\&|SIMRS|RSUD_SEHAT|DCM4CHEE|DCM4CHEE|202501151200||ORM^O01|MSG002|P|2.5
PID|1||P00456^^^DCM4CHEE||WATI^SRI||19900120|F|||JL.SUDIRMAN NO.5^^BANDUNG^^^
PV1|1|I|RAD^R001^|||DR.ANDI|||RAD001
ORC|NW|ACC2025001|||SC
OBR|1|ACC2025001||MRI^MRI BRAIN|||202501151600|||RAD001|||MRI_GE
ZDS|1.2.840.10008.1.2|1.2.840.10008.5.1.4.1.1.4.0
EOF

echo -ne "$(cat /tmp/orm-order2.hl7)\r\n" | nc -w 5 localhost 2762
```

> **Tips:** `ACC2025001` di `OBR.3` adalah Accession Number — identifier unik untuk order ini.

**12c. Cek MWL entry terbuat**

```bash
# Via REST API
curl -s "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems?PatientID=P00123" | jq .

# Via Web UI
# Queue → MWL → cari Patient ID "P00123"
```

**12d. Modality tarik worklist**

Di sisi modality (CT_SIEMENS), teknolog akan melakukan:
- Pilih menu **Worklist** / **MWL Query**
- AE Title tujuan: `DCM4CHEE`
- Filter: tanggal hari ini
- Hasil: daftar pasien yang akan di-scan

---

#### ✅ Output Sukses

MWL entry terbuat dengan data sesuai HL7 yang dikirim. Status: `SCHEDULED`.

Verifikasi via REST:

```json
[
  {
    "00400100": {"vr": "SQ", "Value": [{
      "00400001": {"vr": "AE", "Value": ["CT_SIEMENS"]},
      "00400002": {"vr": "DA", "Value": ["20250115"]},
      "00400003": {"vr": "TM", "Value": ["140000"]}
    }]},
    "00100020": {"vr": "LO", "Value": ["P00123"]},
    "00100010": {"vr": "PN", "Value": [{"Alphabetic": "SUSANTO^BUDI"}]}
  }
]
```

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| Tidak ada response dari nc | HL7 receiver mati atau port salah | Cek `ss -tlnp \| grep 2762` |
| `PID.3` dengan namespace `^^^` salah format | Format HL7 tidak standar | Gunakan `P00123^^^DCM4CHEE` |
| MWL entry terbuat tapi kosong | HL7 message tidak di-parse benar | Cek log: `docker logs dcm4chee-arc \| grep -i "HL7\|ORM"` |
| Modality tidak bisa tarik worklist | MWL SCP belum di-set | Enable MWL SCP di Web UI |
| `ORC.1` = `CA` order cancel | Ada cancel order | Kirim `ORC` dengan `NW` untuk new order |

**Solusi: test koneksi HL7 dulu:**

```bash
# Kirim echo ke HL7 port
echo -ne "\x0b\x00\x1c\x1c" | nc -w 3 localhost 2762

# Response: port terbuka (tidak ada error)
# Atau: tidak ada response tapi nc exit code 0 = sukses kirim
```

---

### Case 13: Kirim Data Pasien via HL7 ADT

**Tujuan:** Mengirim data admisi/update pasien dari HIS ke archive.

**Alat:** `nc` (netcat).

#### Langkah-Langkah

**13a. Kirim ADT^A01 (Patient Admission — pasien masuk)**

```bash
cat > /tmp/adt-a01.hl7 << 'EOF'
MSH|^~\&|SIMRS|RSUD_SEHAT|DCM4CHEE|DCM4CHEE|202501150800||ADT^A01|ADT001|P|2.5
EVN|A01|202501150800
PID|1||P00123^^^DCM4CHEE||SUSANTO^BUDI||19850515|M|||JL.MERDEKA NO.10^^JAKARTA^^^||08123456789
PV1|1|I|RAN^A101^01|||DR.ANDI^^^|||RAD001
EOF

echo -ne "$(cat /tmp/adt-a01.hl7)\r\n" | nc -w 5 localhost 2575
```

**13b. Kirim ADT^A08 (Patient Update — update data pasien)**

```bash
cat > /tmp/adt-a08.hl7 << 'EOF'
MSH|^~\&|SIMRS|RSUD_SEHAT|DCM4CHEE|DCM4CHEE|202501151000||ADT^A08|ADT002|P|2.5
EVN|A08|202501151000
PID|1||P00123^^^DCM4CHEE||SUSANTO^BUDI^^^||19850515|M|||JL.GAJAH MADA NO.5^^JAKARTA^^^||08129876543
EOF

echo -ne "$(cat /tmp/adt-a08.hl7)\r\n" | nc -w 5 localhost 2575
```

**13c. Kirim ADT^A40 (Patient Merge — pasien pindah ID)**

```bash
cat > /tmp/adt-a40.hl7 << 'EOF'
MSH|^~\&|SIMRS|RSUD_SEHAT|DCM4CHEE|DCM4CHEE|202501151100||ADT^A40|ADT003|P|2.5
EVN|A40|202501151100
PID|1||P99999^^^DCM4CHEE||SUSANTO^BUDI||19850515|M
MRG|1||P00123^^^DCM4CHEE
EOF

echo -ne "$(cat /tmp/adt-a40.hl7)\r\n" | nc -w 5 localhost 2575
```

> **Fungsi ADT^A40:** Menggabungkan dua pasien. Semua study milik `P00123` dipindahkan ke `P99999`. `P00123` menjadi tidak aktif.

**13d. Verifikasi data pasien**

```bash
# Cari pasien via REST API
curl -s "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/patients?PatientID=P00123" | jq .
```

---

#### ✅ Output Sukses

Data pasien terupdate. Response dari log:

```
HL7 message received: ADT^A01
Patient P00123 created/updated successfully
```

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `Message type ADT^A01 not supported` | Message type tidak terdaftar | Tambahkan di Web UI → HL7 Service (ADT) → Message Types |
| PID tidak berubah setelah A08 | Format HL7 salah | Cek segment PID.5 (nama) dan PID.11 (alamat) |
| Merge gagal | Patient ID tidak ditemukan | Pastikan `P00123` ada di database |
| `HL7 Parse Error: Missing segment` | HL7 format tidak lengkap | Wajib ada MSH, EVN, PID |

---

### Case 14: Retrieve Gambar via DICOM C-MOVE

**Tujuan:** Mengambil gambar dari archive dan mengirimkannya ke client/viewer.

**Alat:** `getscu` (dcm4che toolkit) atau DICOM viewer.

#### Langkah-Langkah

**14a. Siapkan receiver (client yang akan menerima gambar)**

Buka terminal 1 (sebagai receiver):

```bash
# storescp akan menerima file DICOM yang dikirim oleh archive
storescp 11113 -aet MY_VIEWER

# Terminal ini harus tetap terbuka selama proses retrieve
```

| Parameter | Arti |
|-----------|------|
| `11113` | Port tempat client menerima gambar |
| `-aet MY_VIEWER` | AE Title client (harus terdaftar di archive) |

**14b. Cari Study UID yang akan di-retrieve**

```bash
curl -s "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=5" | \
  jq -r '.[] | ."0020000D"."Value"[0]'

# Output contoh:
# 1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047
```

**14c. Retrieve via C-MOVE**

Buka terminal 2:

```bash
# Format:
# getscu <archive_host> <archive_port>
#   -aet <calling_ae>
#   -aec <called_ae>
#   -aecm <move_destination_ae>

getscu localhost 11112 \
  -aet MY_VIEWER \
  -aec DCM4CHEE \
  --study 1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047
```

**14d. Tunggu gambar masuk di receiver**

Di terminal 1 (storescp), akan terlihat file DICOM masuk satu per satu.

---

#### ✅ Output Sukses

Terminal 2 (getscu):
```
I: C-MOVE request sent
I: Waiting for C-MOVE responses...
I: Completed: Status 0x0000 (Success)
I: Remaining: 0, Completed: 150, Failed: 0, Warning: 0
```

Terminal 1 (storescp):
```
I: Received C-STORE request
I: Storing: /tmp/dcm4chee/store/CT.1.2.840.dcm
I: C-STORE response: Success
```

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `Unable to send C-MOVE to destination` | `MY_VIEWER` tidak dikenal archive | Tambahkan `MY_VIEWER` sebagai AE Title (Host=IP client, Port=11113) |
| `Association Refused: called AE not recognized` | `DCM4CHEE` tidak dikenal | Pastikan `-aec DCM4CHEE` |
| `No matching studies found` | Study UID salah | Cek UID dari QIDO-RS |
| Client tidak terima gambar | Firewall blok incoming ke port 11113 | `sudo ufw allow 11113/tcp` |
| storescp "Address already in use" | Port 11113 sudah dipakai | Ganti port: `storescp 11114 -aet MY_VIEWER` |

**Testing C-MOVE dengan viewer (Radiant/Weasis):**
1. Buka DICOM viewer (Radiant)
2. Add server: Host=IP archive, Port=11112, AE=DCM4CHEE
3. Search study
4. Right click → **Retrieve**
5. Pilih destination: AE viewer Anda sendiri

---

## Bagian 4: Manajemen & Perawatan

---

### Case 15: Backup Database PostgreSQL

**Tujuan:** Membackup metadata DICOM (tabel) ke file SQL.

**Alat:** `pg_dump` (via docker exec).

#### Langkah-Langkah

**15a. Backup full database**

```bash
# Buat direktori backup
mkdir -p ~/dcm4chee-training/backup/db

# Backup via pg_dump
docker exec dcm4chee-db pg_dump \
  -U pacs \
  -d pacsdb \
  -F c \
  -f /backup/db/pacsdb-$(date +%Y%m%d_%H%M%S).dump

# Penjelasan:
# -U pacs      = user database
# -d pacsdb    = nama database
# -F c         = format custom (compressed)
# -f /backup/...  = path di DALAM container (mount ke ./backup/db)
```

**15b. Cek file backup terbuat**

```bash
ls -lh ~/dcm4chee-training/backup/db/
# Output: pacsdb-20250115_120000.dump  (size: 15MB)
```

**15c. Backup otomatis via cron (setiap hari jam 2 pagi)**

```bash
crontab -e
# Tambahkan baris:
0 2 * * * docker exec dcm4chee-db pg_dump -U pacs -d pacsdb -F c -f /backup/db/pacsdb-$(date +\%Y\%m\%d).dump
```

**15d. Backup juga file storage (DICOM images)**

```bash
# Backup storage via rsync
rsync -avz ~/dcm4chee-training/storage_data/ \
  /mnt/backup/dcm4chee-storage-$(date +%Y%m%d)/
```

---

#### ✅ Output Sukses

```
pg_dump: dumping contents of table "patients"
pg_dump: dumping contents of table "studies"
pg_dump: dumping contents of table "series"
pg_dump: dumping contents of table "instances"
pg_dump: dumping contents of table "mwl_items"
pg_dump: finished with success
```

File backup terbuat dengan size sesuai jumlah data.

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `pg_dump: error: connection to database failed` | Container DB tidak bisa diakses | Cek `docker ps`, pastikan dcm4chee-db running |
| `FATAL: password authentication failed` | Password DB salah | Cek `POSTGRES_PASSWORD` di .env |
| `Permission denied` saat write backup | Path mount tidak writable | Pastikan ./backup/db ada: `mkdir -p backup/db` |
| File 0 bytes | Gagal backup | Cek disk space: `df -h` |

---

### Case 16: Restore Database dari Backup

**Tujuan:** Mengembalikan database dari file backup.

**Alat:** `pg_restore` (via docker exec).

#### Langkah-Langkah

**16a. Stop archive (penting — agar tidak ada koneksi aktif ke DB)**

```bash
docker stop dcm4chee-arc
```

**16b. Hapus database lama dan buat ulang**

```bash
# Drop dan recreate database
docker exec dcm4chee-db psql -U pacs -c "DROP DATABASE IF EXISTS pacsdb;"
docker exec dcm4chee-db psql -U pacs -c "CREATE DATABASE pacsdb OWNER pacs;"
```

**16c. Restore dari file backup**

```bash
# Cari file backup terbaru
ls -lt ~/dcm4chee-training/backup/db/
# Pilih file yang akan di-restore

# Restore
docker exec dcm4chee-db pg_restore \
  -U pacs \
  -d pacsdb \
  -F c \
  -v \
  /backup/db/pacsdb-20250115_120000.dump
```

**16d. Start ulang archive**

```bash
docker start dcm4chee-arc

# Cek status
docker ps --format "table {{.Names}}\t{{.Status}}"
```

---

#### ✅ Output Sukses

```
pg_restore: processing data for table "patients"
pg_restore: processing data for table "studies"
pg_restore: processing data for table "series"
pg_restore: processing data for table "instances"
pg_restore: finished with success
```

Setelah archive start, data lama akan muncul di Web UI.

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `pg_restore: error: could not execute query: ERROR: role "pacs" does not exist` | User tidak ada | Buat user dulu: `CREATE USER pacs;` |
| `ERROR: relation "patients" already exists` | Database belum di-drop | JALANKAN drop database dulu |
| `pg_restore: error: input file seems invalid` | File backup corrupt | Coba file backup lain, atau backup ulang |
| Archive error setelah restore | Cache WildFly tidak cocok | `docker restart dcm4chee-arc` |

**Peringatan penting:**
- Restore akan **menimpa semua data** yang ada
- Backup hanya mengembalikan **metadata**, bukan file DICOM
- File DICOM tetap harus ada di storage, kalau tidak study akan muncul tapi gambar tidak bisa di-load

---

### Case 17: Monitor Storage & Performa

**Tujuan:** Memantau kapasitas storage dan performa sistem.

**Alat:** Docker CLI, psql, cURL.

#### Langkah-Langkah

**17a. Cek storage DICOM (dari container)**

```bash
# Dari dalam container
docker exec dcm4chee-arc df -h /storage
```

**17b. Cek volume size**

```bash
# Docker
docker system df -v | grep -A 5 "dcm4chee"

# Podman
podman volume inspect dcm4chee_storage_data --format '{{.Mountpoint}}'
du -sh $(podman volume inspect dcm4chee_storage_data --format '{{.Mountpoint}}')
```

**17c. Cek database size**

```bash
docker exec dcm4chee-db psql -U pacs -d pacsdb -c "
  SELECT pg_size_pretty(pg_database_size('pacsdb')) as db_size;
"
```

**17d. Cek jumlah data**

```bash
docker exec dcm4chee-db psql -U pacs -d pacsdb -c "
  SELECT 'patients' as tbl, count(*) FROM patients
  UNION ALL SELECT 'studies', count(*) FROM studies
  UNION ALL SELECT 'series', count(*) FROM series
  UNION ALL SELECT 'instances', count(*) FROM instances;
"
```

**17e. Cek performa query**

```bash
docker exec dcm4chee-db psql -U pacs -d pacsdb -c "
  EXPLAIN ANALYZE
  SELECT p.pat_name, s.study_date, s.modality
  FROM patients p
  JOIN studies s ON p.pk = s.pat_id
  WHERE s.study_date >= '2025-01-01'
  LIMIT 20;
"
```

**17f. Cek container resource usage**

```bash
# Docker
docker stats dcm4chee-arc dcm4chee-db dcm4chee-ldap --no-stream

# Podman
podman stats dcm4chee-arc dcm4chee-db dcm4chee-ldap --no-stream
```

---

#### ✅ Output Sukses

```
CONTAINER           CPU %   MEM USAGE / LIMIT   MEM %
dcm4chee-arc        12.5%   1.2GB / 2GB         60%
dcm4chee-db          2.1%   256MB / 4GB          6.4%
dcm4chee-ldap        0.3%    45MB / 512MB        8.8%
```

#### ❌ Kendala & Solusi

| Hasil | Arti | Tindakan |
|-------|------|----------|
| RAM usage > 80% | Heap perlu ditambah | Naikkan `WILDFLY_JAVA_OPTS` |
| Storage > 80% | Hampir penuh | Tambah storage, atau archive old studies |
| Query > 2 detik | Index mungkin kurang | `EXPLAIN ANALYZE` cek full scan |
| `OOMKilled` | Container kehabisan memory | Naikkan `mem_limit` di compose |

---

### Case 18: Reset Password Web UI yang Lupa

**Tujuan:** Mereset password admin Web UI jika lupa.

**Alat:** WildFly CLI (jboss-cli).

#### Langkah-Langkah

**18a. Masuk ke container archive**

```bash
docker exec -it dcm4chee-arc bash
```

**18b. Jalankan WildFly CLI**

```bash
# Di dalam container
/opt/wildfly/bin/jboss-cli.sh -c

# Jika error "connection refused", coba:
/opt/wildfly/bin/jboss-cli.sh -c --controller=localhost:9990
```

**18c. Di prompt jboss-cli, jalankan:**

```bash
# Masuk ke management realm
/core-service=management/security-realm=ApplicationRealm/authentication=local/:read-resource

# Buat user baru
/subsystem=elytron/filesystem-realm=ApplicationRealm:add-identity(identity=adminbaru)
/subsystem=elytron/filesystem-realm=ApplicationRealm:set-password(identity=adminbaru, clear={password=passwordbaru123})
/subsystem=elytron/filesystem-realm=ApplicationRealm:add-identity-attribute(identity=adminbaru, name=groups, value=["auth","root","admin","auditlog"])

# Exit
quit
```

**18d. Alternatif: reset via LDAP (jika user disimpan di LDAP)**

```bash
# Cek user di LDAP
docker exec dcm4chee-ldap ldapsearch -x -b "ou=users,dc=dcm4che,dc=org"

# Reset password user root
# Buat file LDIF
cat > /tmp/reset-pwd.ldif << 'EOF'
dn: cn=root,ou=users,dc=dcm4che,dc=org
changetype: modify
replace: userPassword
userPassword: {SSHA}newhashedpassword
EOF

# Apply
docker exec -i dcm4chee-ldap ldapmodify -x -D "cn=admin,dc=dcm4che,dc=org" -w secret -f /tmp/reset-pwd.ldif
```

**18e. Keluar dari container**

```bash
exit
```

**18f. Restart archive**

```bash
docker restart dcm4chee-arc
```

---

#### ✅ Output Sukses

```
{
    "outcome" => "success",
    "result" => {"default-user" => "$local"}
}
```

Setelah restart, login dengan user baru `adminbaru` / `passwordbaru123`.

#### ❌ Kendala & Solusi

| Error | Arti | Solusi |
|-------|------|--------|
| `jboss-cli.sh: command not found` | Path salah | Coba `/opt/wildfly/bin/jboss-cli.sh` |
| `The controller is not available` | WildFly management port tidak terbuka | Cek port 9990: `ss -tlnp \| grep 9990` |
| `Permission denied` | User tidak punya hak admin | Login sebagai `root` dulu |

---

## Bagian 5: Troubleshooting Lanjutan

---

### Case 19: Debug Error 500 di Web UI

**Tujuan:** Mendiagnosis dan memperbaiki error 500 Internal Server Error.

**Alat:** Docker logs, API test, LDAP query.

#### Langkah-Langkah

**19a. Identifikasi error dari logs archive**

```bash
# Cari error terbaru
docker logs dcm4chee-arc --tail 100 2>&1 | grep -A 10 "ERROR\|500\|Internal Server Error"
```

**19b. Klasifikasi error**

| Pola Error | Kemungkinan Penyebab |
|-----------|---------------------|
| `javax.naming.AuthenticationException` | LDAP password salah |
| `PSQLException: connection refused` | Database tidak bisa diakses |
| `javax.persistence.OptimisticLockException` | Database lock |
| `java.lang.OutOfMemoryError` | Heap Java habis |
| `FileNotFoundException: /storage/...` | Storage path salah |
| `java.lang.IllegalArgumentException: UID` | Study UID invalid |

**19c. Cek koneksi LDAP**

```bash
# Verifikasi LDAP bisa diakses dengan credential saat ini
docker exec dcm4chee-ldap ldapwhoami -x \
  -H ldap://localhost \
  -D "cn=admin,dc=dcm4che,dc=org" \
  -w secret

# Cek apakah archive bisa connect ke LDAP
docker exec dcm4chee-arc ldapsearch -x \
  -H ldap://ldap:389 \
  -D "cn=admin,dc=dcm4che,dc=org" \
  -w secret \
  -b "dc=dcm4che,dc=org" \
  "objectclass=*" 2>&1 | head -20
```

**19d. Cek koneksi database**

```bash
# Verifikasi DB bisa diakses
docker exec dcm4chee-db pg_isready -U pacs -d pacsdb

# Cek connection dari archive ke DB
docker exec dcm4chee-arc bash -c "nc -zv db 5432"
```

**19e. Cek apakah masalah ada di konfigurasi LDAP (penyebab 500 paling sering)**

```bash
# Cek device name di LDAP
docker exec dcm4chee-ldap ldapsearch -x \
  -b "cn=Devices,cn=DCM4CHEE,cn=Config,dc=dcm4che,dc=org"

# Cek apakah ada device name yang tidak match
# Archive device name HARUS sama dengan yang ada di LDAP
# Default LDAP bootstrap = dcm4chee-arc
```

**19f. Test endpoint langsung (bypass Web UI)**

```bash
# Test REST API langsung
curl -v "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=1"

# Kalau REST API berhasil, masalah ada di UI/config browser
# Kalau REST API juga 500, masalah di backend
```

**19g. Restart archive**

```bash
# Kadang restart bisa fix error sementara
docker restart dcm4chee-arc
# Tunggu 3-5 menit sampai healthy
```

---

#### ✅ Output Sukses

```
HTTP 200 — Web UI normal kembali
```

#### ❌ Kendala & Solusi

| Root Cause | Fix |
|------------|-----|
| LDAP password mismatch | Pastikan `LDAP_PASSWORD` di semua service sama. Default: `secret` |
| `ARCHIVE_DEVICE_NAME` salah | **Hapus** env variable ini. Biarkan pakai default LDAP = `dcm4chee-arc` |
| DB connection string salah | Cek `POSTGRES_DB/USER/PASSWORD` match dengan yang di DB |
| Storage path tidak ada | Cek `ARCHIVE_STORAGE_DIR` di environment archive |
| WildFly belum fully deployed | Tunggu log "WFLYSRV0025: WildFly 34.0.0.Final started" |

**Skenario: error 500 karena LDAP password mismatch:**

```bash
# 1. Cek password di LDAP
docker exec dcm4chee-ldap ldapsearch -x \
  -D "cn=admin,dc=dcm4che,dc=org" -w secret \
  -b "dc=dcm4che,dc=org" "objectclass=*" | head -5

# 2. Jika gagal → password LDAP bukan "secret"
#    Reset password LDAP ke default:
docker exec dcm4chee-ldap ldapmodify -x \
  -D "cn=admin,dc=dcm4che,dc=org" -w oldpassword \
  <<< 'dn: cn=admin,dc=dcm4che,dc=org
changetype: modify
replace: userPassword
userPassword: secret'

# 3. Update .env dan restart
sed -i 's/LDAP_PASSWORD=.*/LDAP_PASSWORD=secret/' .env
docker compose -f docker-compose.level1-basic.yml up -d --force-recreate
```

---

### Case 20: Container Crash Loop — Diagnosis & Solusi

**Tujuan:** Menangani container yang restart terus-menerus (crash loop).

**Alat:** Docker inspect, logs, system resources.

#### Langkah-Langkah

**20a. Identifikasi container yang crash**

```bash
# Cek semua container (termasuk yang mati)
docker ps -a --format "table {{.Names}}\t{{.Status}}\t{{.RestartCount}}"
```

Restart count tinggi (>5) menandakan crash loop.

**20b. Cek alasan kenapa container exit**

```bash
# Cek exit code
docker inspect dcm4chee-arc --format '{{.State.ExitCode}}'

# Arti exit code:
# 0   = normal exit
# 1   = application error
# 137 = SIGKILL (OOM, atau manual kill)
# 139 = SIGSEGV (segmentation fault)
# 143 = SIGTERM (normal termination)

# Cek restart policy
docker inspect dcm4chee-arc --format '{{.HostConfig.RestartPolicy.Name}}'
# unless-stopped → Docker akan restart otomatis
```

**20c. Cek OOM (Out of Memory)**

```bash
# Cek apakah container di-kill karena OOM
docker inspect dcm4chee-arc --format '{{.State.OOMKilled}}'
# Output: true = ya, OOM

# Cek memory limit
docker inspect dcm4chee-arc --format '{{.HostConfig.Memory}}'
# 0 = unlimited

# Cek system memory
free -h
```

**20d. Lihat log sebelum crash**

```bash
# Log dari container restart terakhir
docker logs dcm4chee-arc --tail 50 --timestamps
```

**20e. Untuk setiap penyebab, solusi spesifik**

**Jika OOMKilled = true:**
```yaml
# Tambah memory limit di docker-compose.yml
services:
  arc:
    mem_limit: 4g
    mem_reservation: 2g
    environment:
      WILDFLY_JAVA_OPTS: "-Xms2g -Xmx3g -XX:+UseG1GC"
```

**Jika exit code 1 (application error):**
```bash
# Lihat error detail
docker logs dcm4chee-arc --tail 100 2>&1 | grep -i "exception\|error"

# Biasanya karena:
# 1. LDAP connection failed → cek LDAP container
# 2. DB connection failed → cek DB container
# 3. Port conflict → cek port
```

**Jika LDAP crash loop:**
```bash
# Cek log LDAP
docker logs dcm4chee-ldap --tail 50

# Error umum: LDAP_ROOTPASS atau LDAP_CONFIGPASS tidak diset
# Solusi: tambahkan env variable ini
```

**Jika DB crash loop:**
```bash
# Cek log DB
docker logs dcm4chee-db --tail 50

# Error umum: data directory permissions
# Solusi:
sudo chown -R 70:70 ~/dcm4chee-training/.data/db

# Error umum: port 5432 dipakai
sudo ss -tlnp | grep 5432
```

**20f. Reset total (jika semua gagal)**

```bash
# Hapus semua container dan volume, mulai dari awal
cd ~/dcm4chee-training

docker compose -f docker-compose.level1-basic.yml down -v
docker system prune -f --volumes

# Start fresh
docker compose -f docker-compose.level1-basic.yml up -d
```

---

#### ✅ Output Sukses

Setelah perbaikan, container berjalan normal:
```
NAMES            STATUS                    RESTART COUNT
dcm4chee-ldap    Up 2 hours (healthy)      0
dcm4chee-db      Up 2 hours (healthy)      0
dcm4chee-arc     Up 2 hours (healthy)      0
```

#### ❌ Kendala & Solusi

| Skenario | Diagnosis | Solusi |
|---------|-----------|--------|
| Container restart > 10 kali dalam 5 menit | Crash loop | `docker inspect` cek exit code, log, OOM |
| Exit code 137 + OOMKilled true | Kehabisan memory | Naikkan `mem_limit` dan `WILDFLY_JAVA_OPTS` |
| Exit code 137 + OOMKilled false | Di-kill manual / systemd | Cek `journalctl -xe`, mungkin system out of memory |
| Exit code 1 + log "address already in use" | Port conflict | Ganti port mapping atau stop service lain |
| Container "Created" (tidak mulai-mulai) | Depends_on tidak terpenuhi | Pastikan LDAP & DB healthy dulu |
| Container restart setelah save di Web UI | Normal (WildFly reload) | Biarkan, akan normal dalam 30-60 detik |

**Script diagnosis cepat:**

```bash
#!/bin/bash
echo "=== Crash Loop Diagnosis ==="
for c in dcm4chee-ldap dcm4chee-db dcm4chee-arc; do
  echo ""
  echo "--- $c ---"
  STATUS=$(docker inspect $c --format '{{.State.Status}}' 2>/dev/null)
  EXIT=$(docker inspect $c --format '{{.State.ExitCode}}' 2>/dev/null)
  OOM=$(docker inspect $c --format '{{.State.OOMKilled}}' 2>/dev/null)
  RESTART=$(docker inspect $c --format '{{.RestartCount}}' 2>/dev/null)
  echo "Status: $STATUS | Exit: $EXIT | OOM: $OOM | Restarts: $RESTART"
done
```

---

## Lampiran: Daftar Perintah Cepat

| Tujuan | Perintah |
|--------|----------|
| Start semua | `docker compose -f docker-compose.level1-basic.yml up -d` |
| Stop semua | `docker compose -f docker-compose.level1-basic.yml down` |
| Cek status | `docker ps --format "table {{.Names}}\t{{.Status}}"` |
| Cek health | `docker inspect dcm4chee-arc --format '{{.State.Health.Status}}'` |
| Lihat log | `docker logs -f dcm4chee-arc --tail 50` |
| Masuk container | `docker exec -it dcm4chee-arc bash` |
| C-ECHO DICOM | `echoscu localhost 11112 -aet TEST -aec DCM4CHEE` |
| Kirim via STOW-RS | `curl -X POST --data-binary @file.dcm -H "Content-Type: application/dicom" "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"` |
| Query study | `curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientName=SUSANTO"` |
| Backup DB | `docker exec dcm4chee-db pg_dump -U pacs -d pacsdb -F c -f /backup/db/backup.dump` |
| Restart archive | `docker restart dcm4chee-arc` |

---

*Panduan penggunaan DCM4CHEE Archive 5.34.2*
*Mencakup Docker & Podman runtime*
*Terakhir diperbarui: Mei 2026*
