# LEVEL 1: BASIC — Fondasi Implementasi DCM4CHEE

## Tujuan Pembelajaran

Setelah menyelesaikan level ini, Anda akan mampu:
1. Memahami konsep dasar DICOM dan perannya dalam healthcare IT
2. Menginstall DCM4CHEE Archive 5.x menggunakan Docker
3. Mengakses dan menavigasi web interface
4. Melakukan verifikasi konektivitas DICOM dasar dengan modality/validator

---

## Modul 1: Konsep Dasar DICOM

### 1.1 Apa Itu DICOM?

**DICOM (Digital Imaging and Communications in Medicine)** adalah standar internasional untuk menyimpan, retrieve, print, dan share gambar medis. Standar ini memastikan semua perangkat medis (CT, MRI, X-Ray, USG) bisa "berbicara" satu sama lain.

**Mengapa penting?** Bayangkan RS Anda punya 3 modality dari vendor berbeda (GE, Siemens, Philips). Tanpa DICOM, setiap vendor butuh integration khusus. Dengan DICOM, semua modality bisa kirim gambar ke satu sistem PACS menggunakan bahasa yang sama.

### 1.2 Komponen Inti DICOM

```
┌─────────────────────────────────────────────────────────┐
│                  DICOM Model Data                       │
├─────────────────────────────────────────────────────────┤
│  Patient (ID, Nama, DOB, Jenis Kelamin)                │
│    └── Study (Study UID, Tanggal, Modalitas, dokter)   │
│          └── Series (Series UID, modalitas, deskripsi)  │
│                └── Instance (SOP UID, image)           │
│                     + Metadata DICOM header            │
└─────────────────────────────────────────────────────────┘
```

**Metadata penting yang perlu Anda ketahui:**

| Tag DICOM | Nama | Deskripsi |
|-----------|------|-----------|
| `0008,0020` | Study Date | Tanggal study |
| `0010,0020` | Patient ID | ID pasien unik |
| `0010,0010` | Patient Name | Nama pasien |
| `0008,0060` | Modality | Jenis modalitas (CT, MR, US, XA, CR, DX) |
| `0020,000D` | Study Instance UID | UID unik study |
| `0020,000E` | Series Instance UID | UID unik series |
| `0008,0018` | SOP Instance UID | UID unik instance/gambar |

### 1.3 DICOM Services (SOP Classes)

DICOM mendefinisikan services berupa command yang dikirim antar sistem:

```
┌──────────────┬─────────────────────────────────────────────────┐
│ Service      │ Fungsi                                         │
├──────────────┼─────────────────────────────────────────────────┤
│ C-STORE      │ Simpan/sender image ke archive/PACS            │
│ C-FIND       │ Query data (patient, study, series, instance)   │
│ C-MOVE       │ Retrieve/minta image kirim ke AE tujuan         │
│ C-ECHO       │ Verifikasi koneksi (ping DICOM)                 │
│ C-GET        │ Retrieve dengan pull langsung oleh requestor    │
│ MWL (C-FIND) │ Modality Worklist - daftar pekerjaan modality   │
│ MPPS         │ Modality Performed Procedure Step - status     │
│ STOW-RS      │ Store via HTTP/ REST (modern alternative)      │
│ QIDO-RS      │ Query via HTTP/ REST                           │
│ WADO-RS      │ Retrieve via HTTP/ REST                        │
└──────────────┴─────────────────────────────────────────────────┘
```

### 1.4 AE Title — Identitas Node DICOM

**AE Title (Application Entity Title)** adalah "nama" dari setiap node DICOM di jaringan, maksimal 16 karakter. Setiap perangkat DICOM di jaringan harus punya AE title unik.

**Contoh:**
- Modality CT: `CT_SIEMENS`
- Modality MRI: `MRI_GE`
- PACS/Archive: `DCM4CHEE`
- Radiologist Viewer: `OHIF_VIEWER`

**Komunikasi DICOM terjadi ketika:**
1. Client (SCU - Service Class User) membuka koneksi ke Server (SCP - Service Class Provider)
2. Client bilang: "Saya AE `CT_SIEMENS`, mau kirim gambar ke `DCM4CHEE` port 11112"
3. Server (SCP) cek: "Siapa `CT_SIEMENS`? Sudah saya kenal belum?"
4. Kalau belum kenal → reject association
5. Kalau sudah kenal → accept, lalu transfer data

---

## Modul 2: Arsitektur DCM4CHEE Archive

### 2.1 Overview Arsitektur

DCM4CHEE Archive 5.x adalah Java EE application yang berjalan di **WildFly application server**, menggunakan arsitektur berikut:

```
┌────────────────────────────────────────────────────────────────────┐
│                    DCM4CHEE Archive 5.x                          │
│                   (WildFly Application Server)                   │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │ Storage SCP │  │ QR SCP       │  │ MWL SCP     │            │
│  │ (C-STORE)   │  │ (C-FIND/     │  │ (Worklist)  │            │
│  │ Terima image│  │  C-MOVE)     │  │             │            │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘            │
│         │                 │                 │                    │
│  ┌──────▼─────────────────▼─────────────────▼──────┐            │
│  │              RESTful Services                    │            │
│  │  STOW-RS  │  QIDO-RS  │  WADO-RS  │  WADO-URI  │            │
│  └──────┬─────────────────┬─────────────────┬───────┘            │
│         │                 │                 │                    │
│  ┌──────▼─────────────────▼─────────────────▼──────┐            │
│  │           Web UI (HTML5 compliant)             │            │
│  └─────────────────────────────────────────────────┘            │
│                           │                                      │
│  ┌─────────────────────────▼─────────────────────────┐          │
│  │         WildFly Management / JMX Console         │          │
│  └─────────────────────────────────────────────────┘          │
└────────────────────────────────────────────────────────────────────┘
         │                    │                    │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │ OpenLDAP│         │PostgreSQL│        │ Storage │
    │(Config) │         │  (DB)    │        │(Files)  │
    │  :389   │         │  :5432   │        │ /path   │
    └─────────┘         └──────────┘        └─────────┘
```

### 2.2 Database Schema (PostgreSQL)

Database PostgreSQL menyimpan **metadata** DICOM (bukan file gambar itu sendiri). File gambar disimpan di filesystem storage.

```
┌─────────────────────────────────────────────────────────────────┐
│                 PostgreSQL Database Schema                     │
├─────────────────────────────────────────────────────────────────┤
│  patients         — Patient demographics                       │
│  studies         — Study-level metadata                       │
│  series          — Series-level metadata                      │
│  instances       — Instance-level metadata + storage location │
│  mwl_items       — Modality Worklist entries                  │
│  ups             — Unified Procedure Step                   │
│  storage_verif   — Storage commitment verification           │
│  queue           — Internal processing queue                │
│  audit_record    — Audit trail (IHE ATNA compliant)         │
└─────────────────────────────────────────────────────────────────┘
```

**Storage location di DB (`instances` table):**
- Kolom `blob_info` → lokasi file di storage
- File gambar disimpan di: `STORAGE_DIR/{date}/{study_hash}/{series_hash}/{sop_hash}`
- Contoh: `/var/data/dcm4chee/archive/2025/05/18/1.2.840.../1.2.840.../1.2.840...dcm`

### 2.3 LDAP Configuration

DCM4CHEE 5.x menyimpan **semua konfigurasi di LDAP**, bukan di file config atau database biasa. Ini sesuai standar DICOM Part 15 Annex H.

**LDAP entries yang perlu dipahami:**

| DN (Distinguished Name) | Fungsi |
|------------------------|--------|
| `dicomDeviceName=DCM4CHEE,...` | Device-level config (AE titles, ports, dll) |
| `dcmArchiveDevice...` | Archive-specific settings |
| `dcmNetworkAE...` | AE Title definitions ( tiap modality/client) |
| `dcmStorage...` | Storage definitions (path, S3, dll) |
| `dcmTransferCapability...` | Transfer syntax yang didukung |
| `dcmWebApplication...` | Web app configs (QIDO, WADO, STOW) |

---

## Modul 3: Instalasi Docker (Step-by-Step)

### 3.1 Prerequisites

```bash
# Check Docker version
docker --version
# Docker version 24.0+ (minimum), 25.x+ recommended

docker compose version
# Docker Compose version v2.20.0+
```

**Resource requirements (minimum vs recommended):**

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| RAM | 4 GB | 8 GB |
| CPU | 2 cores | 4+ cores |
| Disk | 50 GB | 200+ GB (untuk image DICOM) |
| OS | Ubuntu 22.04 LTS | Ubuntu 22.04/24.04 LTS |

### 3.2 Langkah 1: Buat Direktori Project

```bash
sudo mkdir -p /opt/dcm4chee-arc
cd /opt/dcm4chee-arc
sudo mkdir -p storage wildfly ldap slapd.d db
sudo chown -R 999:999 storage  # uid 999 = wildfly user
sudo chown -R 700 ldap slapd.d
sudo chown -R 700 db
```

**Mengapa uid 999?** Container `dcm4che/dcm4chee-arc-psql` menjalankan WildFly sebagai user dengan uid 999 di dalam container. Jika storage directory dimiliki oleh user lain, WildFly tidak bisa menulis file DICOM.

### 3.3 Langkah 2: Buat docker-compose.yml

```bash
cd /opt/dcm4chee-arc
cat > docker-compose.yml << 'EOF'
version: "3.8"

services:
  ldap:
    image: dcm4che/slapd-dcm4chee:2.6.10-34.2
    container_name: dcm4chee-ldap
    environment:
      SLAPD_PASSWORD: changeit
      SLAPD_DOMAIN: dcm4che.org
      SLAPD_ORGANIZATION: dcm4che
      STORAGE_DIR: /storage/fs1
    ports:
      - "389:389"
    volumes:
      - ./ldap:/var/lib/openldap/openldap-data
      - ./slapd.d:/etc/openldap/slapd.d
    healthcheck:
      test: ["CMD", "ldapwhoami", "-x", "-H", "ldap://localhost", "-D", "cn=admin,dc=dcm4che,dc=org", "-w", "changeit"]
      interval: 30s
      timeout: 10s
      retries: 5
    restart: unless-stopped

  db:
    image: dcm4che/postgres-dcm4chee:17.4-34
    container_name: dcm4chee-db
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: pacs
    ports:
      - "5432:5432"
    volumes:
      - ./db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U pacs -d pacsdb"]
      interval: 30s
      timeout: 10s
      retries: 5
    restart: unless-stopped

  arc:
    image: dcm4che/dcm4chee-arc-psql:5.34.2
    container_name: dcm4chee-arc
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: pacs
      WILDFLY_CHOWN: /storage
      WILDFLY_WAIT_FOR: ldap:389 db:5432
    ports:
      - "8080:8080"
      - "8443:8443"
      - "9990:9990"
      - "9993:9993"
      - "11112:11112"
      - "2762:2762"
      - "2575:2575"
      - "12575:12575"
    volumes:
      - ./wildfly:/opt/wildfly/standalone
      - ./storage:/storage
    depends_on:
      ldap:
        condition: service_healthy
      db:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/dcm4chee-arc/ui2/"]
      interval: 60s
      timeout: 30s
      retries: 10
      start_period: 300s
    restart: unless-stopped

networks:
  default:
    name: dcm4chee_network
EOF
```

### 3.4 Langkah 3: Jalankan Container

```bash
cd /opt/dcm4chee-arc

# Pull images
docker compose pull

# Start services
docker compose up -d

# Monitor startup
docker compose logs -f
# Tekan Ctrl+C setelah semua container healthy
```

**Mengapa penting memantau logs?** Pada first-run, LDAP perlu diinisialisasi schema, PostgreSQL perlu create schema database, dan WildFly perlu deploy archive application. Proses ini memakan waktu **3-10 menit** tergantung kecepatan mesin. Anda harus tunggu "INFO [org.jboss.as] (ServerService Thread Pool -- 64) WFLYSRV0025: WildFly 34.0.0.Final started" di logs sebelum akses web UI.

### 3.5 Langkah 4: Verifikasi Status

```bash
# Cek status semua container
docker compose ps

# Output yang diharapkan:
# NAME                IMAGE                          STATUS
# dcm4chee-ldap       dcm4che/slapd-dcm4chee:2.6.10  Up (healthy)
# dcm4chee-db         dcm4che/postgres-dcm4chee:17.4 Up (healthy)
# dcm4chee-arc        dcm4che/dcm4chee-arc-psql:5.34 Up (healthy)

# Cek health check arc
docker inspect dcm4chee-arc --format '{{.State.Health.Status}}'
# Output: healthy

# Cek WildFly sudah running
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/dcm4chee-arc/ui2/
# Output: 200 (berhasil)
```

### 3.6 Langkah 5: Akses Web Interface

**Buka browser, akses:**

```
URL: http://<server-ip>:8080/dcm4chee-arc/ui2
```

**Default credentials:**

| Field | Value |
|-------|-------|
| Name | root |
| Password | changeit |
| Roles | auth, root, auditlog, ADMINISTRATOR |

**Halaman-halaman penting di Web UI:**

| Menu | Fungsi |
|------|--------|
| patients | Browse/search patient |
| studies | Browse/search study |
| Queue | Monitor processing queue |
| Monitor | Monitoring dashboard |
| Configuration | Sistem & device configuration |
| Admin | User management, export rules, dll |

### 3.7 Ports yang Diparkan

| Port | Protocol | Service |
|------|---------|---------|
| `8080` | HTTP | Archive Web UI & REST API |
| `8443` | HTTPS | Secured Web UI (kalau pakai SSL) |
| `9990` | HTTP | WildFly Administration Console |
| `9993` | HTTPS | Secured WildFly Console |
| `11112` | DICOM | Storage SCP, Query/Retrieve SCP |
| `2762` | HL7 MLLP | HL7 Receiver (ORM orders) |
| `2575` | HL7 MLLP | HL7 Receiver (ADT messages) |
| `12575` | HTTP | Keycloak (secured mode) |
| `389` | LDAP | OpenLDAP access |

---

## Modul 4: Hands-On Exercises

### Exercise 1: Verifikasi Koneksi DICOM dengan C-ECHO

**Tujuan:** Memverifikasi DCM4CHEE bisa menerima koneksi dari modality/client.

**Tools yang digunakan:** `dcm4che toolkit` (storescp, echoscp) atau DICOM viewer seperti **OHIF**, **Weasis**, atau **Radiant** (DICOM viewer gratis untuk Windows).

**Langkah dengan DICOM C-ECHO Tool (Radiant/Offline):**

1. Buka DICOM viewer (Radiant: https://www.radiantviewer.com/) atau use `echoscu` dari dcm4che toolkit
2. Konfigurasi connection:

| Field | Value |
|-------|-------|
| AE Title | MY_TEST_SC |
| Host | `<alamat-server-dcm4chee>` |
| Port | `11112` |
| Called AE | `DCM4CHEE` |

3. Klik **C-ECHO / Verify** button
4. **Expected result:** Connection successful, response: `0x0000 (Success)`

**Langkah dengan command line (dcm4che tools):**

```bash
# Install dcm4che tools if not available
# Download from: https://github.com/dcm4che/dcm4che/releases

# Run C-ECHO
./dcm4che/bin/echoscu -bcm4chee localhost 11112 -aet MY_TEST_SC -aec DCM4CHEE

# Expected output:
# I: Requesting Association
# I: Association accepted (max send: 131072)
# I: Received C-ECHO RQ
# I: Sending C-ECHO RSP
# I: Releasing Association
# Result: 0x0000 (Success)
```

**Troubleshooting jika gagal:**

| Error | Cause | Solution |
|-------|-------|----------|
| `Association Rejected: called AE title not recognized` | AE title DCM4CHEE belum di-convoke oleh calling AE | Buka Web UI → Configuration → Network - AE → verifikasi DCM4CHEE exists |
| `Connection refused` | Container belum ready atau port salah | `docker compose logs arc` dan cek port mapping |
| `Timeout` | Firewall block atau container down | Cek `docker compose ps` dan firewall rules |

---

### Exercise 2: Kirim DICOM File ke Archive via STOW-RS

**Tujuan:** Mengirim file DICOM dari modality/client simulator ke archive menggunakan REST API.

**Langkah:**

```bash
# Buat direktori dan download sample DICOM file
mkdir -p /tmp/dcm-test
cd /tmp/dcm-test

# Download sample DICOM file (CT Scan)
curl -LO https://www.dicomserver.co.uk/SampleDICOMFiles/CT/CT.1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047.dcm

# Atau buat DICOM dummy file dari dcm4che tools
# ./dcm4che/bin/dcmsnd +fd /tmp/dcm-test/CT.dcm

# Upload via STOW-RS
curl -v -X POST \
  --data-binary @CT.1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047.dcm \
  -H "Content-Type: application/dicom" \
  "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/stowrs"

# Expected response: HTTP 200 (Success)
```

**Alternatif via Web UI:**

1. Buka `http://localhost:8080/dcm4chee-arc/ui2`
2. Login → menu **studies** → klik **Import** button
3. Upload file DICOM
4. Verifikasi study muncul di list

**Expected result:** Study baru muncul di archive dengan metadata yang benar (Patient Name, Study Date, Modalitas).

---

### Exercise 3: Query Archive via QIDO-RS

**Tujuan:** Retrieve patient/study metadata menggunakan REST API.

**Langkah:**

```bash
# Query semua study (limit 20)
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=20"

# Query study berdasarkan Patient ID
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientID=<PatientID>"

# Query semua series di study tertentu
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies/<StudyUID>/series"

# Query semua instances di series tertentu
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/series/<SeriesUID>/instances"

# Expected result: JSON response dengan metadata DICOM
```

**Output contoh (formatted):**
```json
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
    "Value": [{"Alphetic": "DOE^JOHN"}]
  },
  "00080060": {
    "vr": "CS",
    "Value": ["CT"]
  }
}
```

---

### Exercise 4: Configure External Modality (AE Title)

**Tujuan:** Menambahkan AE title untuk modality baru sehingga archive bisa menerima koneksi dari modality tersebut.

**Langkah via Web UI:**

1. Buka `http://localhost:8080/dcm4chee-arc/ui2` → login
2. Menu: **Configuration** → **Network** → **Network AE**
3. Klik **Create**
4. Isi form:

| Field | Value | Explanation |
|-------|-------|-------------|
| AE Title | `CT_SIEMENS` | AE title modality baru |
| Host Name | `ct-siemens.local` | Hostname atau IP modality |
| Port | `11112` | DICOM port modality |
| Description | `CT Scanner Siemens` | Deskripsi opsional |

5. Scroll ke **Accepted Transfer Syntaxes** → centang:
   - `1.2.840.10008.1.2` (Implicit VR Little Endian)
   - `1.2.840.10008.1.2.1` (Explicit VR Little Endian)
   - `1.2.840.10008.1.2.2` (Explicit VR Big Endian)
   - `1.2.840.10008.1.2.4.50` (JPEG Baseline Process 1)
   - `1.2.840.10008.1.2.4.70` (JPEG Lossless)

6. Klik **Save**
7. Buka menu **Configuration** → **Devices** → **DCM4CHEE**
8. Di section **Other AE Titles**, tambahkan `CT_SIEMENS`
9. Klik **Save** → restart tidak diperlukan, config langsung aktif

**Verifikasi:** Buka terminal, jalankan C-ECHO dari AE title `CT_SIEMENS`:

```bash
./dcm4che/bin/echoscu localhost 11112 \
  -aet CT_SIEMENS \
  -aec DCM4CHEE
```

**Expected result:** Association accepted.

---

## Modul 5: Arsitektur Jaringan & Sistem

### 5.1 Basic Network Architecture

```
                    ┌─────────────────────────────────┐
                    │  DCM4CHEE Archive Container(s)  │
                    │                                  │
                    │  ┌────────────┐  ┌────────────┐ │
                    │  │    ARC     │  │   LDAP     │ │
                    │  │ (WildFly) │  │            │ │
                    │  │  :8080    │  │   :389     │ │
                    │  │  :11112   │  │            │ │
                    │  │  :2575    │  └────────────┘ │
                    │  │  :2762    │                   │
                    │  └─────┬──────┘                   │
                    │        │                          │
                    │  ┌─────▼──────┐  ┌────────────┐ │
                    │  │    DB     │  │  Storage   │ │
                    │  │ PostgreSQL│  │  /storage  │ │
                    │  │   :5432   │  │            │ │
                    │  └───────────┘  └────────────┘ │
                    └─────────────────────────────────┘
                                   │
     ┌─────────────────────────────┼─────────────────────────────┐
     │                             │                             │
┌────▼────┐              ┌────────▼────────┐          ┌─────────▼────────┐
│Modality │              │    DICOM        │          │  Web Browser     │
│  CT     │              │    Client       │          │  (Radiologist)   │
│ AE:     │              │  AE: OHIF       │          │                  │
│CT_SIEMENS              │                  │          │                  │
│Port:11112              │                  │          │                  │
└────┬────┘              └────────┬────────┘          └─────────┬────────┘
     │ DICOM C-STORE /           │ HTTP                      │
     │ C-FIND / C-MOVE           │ QIDO/WADO                 │
     └──────────────────────────┼──────────────────────────┘
                                 │
                    Port mapping host:
                    8080 → Container :8080
                    11112 → Container :11112
                    5432 → Container :5432
                    389 → Container :389
```

### 5.2 DICOM Message Flow (Storage)

```
Modality (CT_SIEMENS)                           DCM4CHEE Archive
       │                                              │
       │──── C-ASSOCIATE-RQ ──────────────────────►  │
       │     Called AE: DCM4CHEE                      │
       │     Calling AE: CT_SIEMENS                   │
       │     Port: 11112                             │
       │◄─── C-ASSOCIATE-AC ────────────────────────  │
       │     Association Accepted                     │
       │                                              │
       │──── C-STORE-RQ ──────────────────────────►  │
       │     SOP Class: CT Image Storage             │
       │     Patient: DOE^JOHN                      │
       │     Study UID: 1.2.840...                  │
       │     [DICOM File / Image Data]              │
       │◄─── C-STORE-RSP ──────────────────────────  │
       │     Status: 0x0000 (Success)              │
       │                                              │
       │──── C-RELEASE-RQ ────────────────────────►  │
       │◄─── C-RELEASE-RP ────────────────────────  │
```

**Step-by-step proses storage:**

1. Modality mengirim `C-ASSOCIATE-RQ` ke port 11112
2. DCM4CHEE lookup LDAP: "Siapa `CT_SIEMENS`? Sudah authorized?"
3. Kalau ya → kirim `C-ASSOCIATE-AC`, otherwise `C-ASSOCIATE-RJ`
4. Modality kirim `C-STORE-RQ` dengan DICOM file
5. DCM4CHEE:
   - Validasi DICOM metadata
   - Extract Patient/Study/Series/Instance UIDs
   - Simpan file ke: `STORAGE_DIR/{date}/{study_hash}/{series_hash}/{sop_hash}.dcm`
   - Insert metadata ke PostgreSQL
6. DCM4CHEE kirim `C-STORE-RSP` dengan status `0x0000` (Success)
7. Modality kirim `C-RELEASE-RQ`, connection ditutup

---

## Modul 6: Studi Kasus — RS Kecil (1-2 Modality)

### 6.1 Profile RS type ini

- **Modalitas:** 1-2 unit (misal: 1 CR/Digital X-Ray, 1 USG)
- **Jumlah study/hari:** < 50 study
- **User radiologist:** 1-3 orang
- **Budget:** Terbatas, perlu solusi hemat resource
- **IT Staff:** 1 orang, non-spesialis DICOM

### 6.2 Kebutuhan Spesifik

| Kebutuhan | Solusi DCM4CHEE |
|-----------|-----------------|
| Terima gambar dari modality | Storage SCP dengan AE title DCM4CHEE |
| Simpan metadata & file | PostgreSQL + filesystem storage |
| Akses gambar untuk reading | QIDO/WADO-RS → integrate OHIF viewer |
| Daftar kerja modality | MWL SCP (optional, kalau ada HL7 interface) |
| Monitoring | Web UI built-in Monitor page |
| Backup | rsync script ke external drive/NAS |

### 6.3 Konfigurasi Kritis untuk RS Kecil

**Storage Configuration:**

```yaml
# docker-compose.yml - Environment untuk arc
services:
  arc:
    environment:
      ARCHIVE_DEVICE_NAME: DCM4CHEE
      ARCHIVE_STORAGE_DIR: /storage/archive
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: pacs
      WILDFLY_CHOWN: /storage
      WILDFLY_WAIT_FOR: ldap:389 db:5432
    volumes:
      - /opt/dcm4chee-arc/storage:/storage
      - /opt/dcm4chee-arc/wildfly:/opt/wildfly/standalone
```

**Storage sizing:**
- CT Scan average: 300-500 MB per study
- CR/Digital X-Ray: 5-20 MB per study
- USG: 10-50 MB per study
- **Estimate:** 50 study/hari × 200 MB = 10 GB/hari × 30 hari = **300 GB/bulan**
- Rekomendasi: **minimal 500 GB storage** untuk RS kecil

**Database sizing:**
- Metadata ~2-5 KB per instance
- 50 study × 100 instance = 5000 instance × 3 KB = **15 MB metadata/month**
- Rekomendasi: **100 GB PostgreSQL data volume** cukup untuk years of data

### 6.4 Step-by-Step Setup RS Kecil

```bash
# 1. Buat direktori
sudo mkdir -p /opt/dcm4chee-arc/{storage,db,ldap,slapd.d,wildfly}
sudo chown 999:999 /opt/dcm4chee-arc/storage

# 2. Buat docker-compose.yml
# (sesuai template di Modul 3)

# 3. Start
docker compose up -d

# 4. Tunggu 5-10 menit, monitor logs
docker compose logs -f arc
# Cari: "WFLYSRV0025: WildFly 34.0.0.Final started"

# 5. Verifikasi
curl -s http://localhost:8080/dcm4chee-arc/ui2/ | grep -o "dcm4chee"

# 6. Setup backup mingguan (rsync)
cat > /opt/backup-dcm4chee.sh << 'SCRIPT'
#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/mnt/backup-nas/dcm4chee/$DATE"
mkdir -p "$BACKUP_DIR"
rsync -avz /opt/dcm4chee-arc/storage "$BACKUP_DIR/storage"
rsync -avz /opt/dcm4chee-arc/db "$BACKUP_DIR/db"
rsync -avz /opt/dcm4chee-arc/wildfly "$BACKUP_DIR/wildfly"
find /mnt/backup-nas/dcm4chee -maxdepth 1 -type d -mtime +30 -exec rm -rf {} \;
SCRIPT
chmod +x /opt/backup-dcm4chee.sh

# 7. Schedule weekly backup
sudo cp /opt/backup-dcm4chee.sh /etc/cron.weekly/
```

### 6.5 Lessons Learned RS Kecil

| Issue | Penyebab | Solusi |
|-------|---------|--------|
| Modality tidak bisa kirim gambar | AE title belum di-convoke | Pastikan calling AE di-add ke Other AE Titles di device config |
| Storage penuh | Estimasinya terlalu kecil | Monitor `df -h` dan alert threshold 80% |
| Database corruption setelah restart | Volume tidak di-unmount dengan benar | Selalu gunakan `docker compose down`, bukan `docker stop` |
| Web UI lambat | RAM kurang dari 4GB | Minimum RAM 4GB, recommend 8GB |
| Container auto-restart terus | Out of memory (OOM) | Increase Docker memory limit |

---

## Modul 7: Troubleshooting & Best Practices Level Basic

### 7.1 Masalah Umum & Solusi

**Masalah 1: Container ARC tidak mau start**

```bash
# Cek logs
docker compose logs arc

# Cause: LDAP/DB belum healthy
# Solution: cek health status
docker compose ps

# Force restart arc setelah DB ready
docker compose restart arc
```

**Masalah 2: C-ECHO gagal / Association Rejected**

```bash
# Cause paling sering: calling AE belum di-convoke
# Solusi: Buka Web UI → Configuration → Network → Network AE
#         Pastikan AE title modality ada dan authorized
```

**Masalah 3: Web UI menampilkan 500 Error**

```bash
# Cek apakah archive sudah fully deployed
docker exec dcm4chee-arc /opt/wildfly/bin/jboss-cli.sh \
  -c ":read-attribute(name=server-state)"

# Expected: running
# Jika started atau unknown → archive belum fully up
# Tunggu lagi 3-5 menit
```

**Masalah 4: Storage permission error**

```bash
# Cek ownership storage directory
ls -la /opt/dcm4chee-arc/storage

# Harus owned oleh 999:999
# Jika tidak:
sudo chown -R 999:999 /opt/dcm4chee-arc/storage
```

**Masalah 5: Port conflict**

```bash
# Cek port sudah dipetakan apa belum
sudo ss -tlnp | grep -E '8080|11112|5432|389'

# Kill process jika perlu
sudo kill $(sudo lsof -t -i:8080)
```

### 7.2 Checklist Pasca-Instalasi

```markdown
## Post-Installation Checklist

### Connectivity
- [ ] C-ECHO dari test client → archive: SUCCESS
- [ ] C-STORE test file → archive: SUCCESS
- [ ] QIDO-RS query metadata: SUCCESS
- [ ] Web UI accessible: http://server:8080/dcm4chee-arc/ui2

### Configuration
- [ ] Default AE title DCM4CHEE aktif
- [ ] External modality AE titles sudah di-add
- [ ] Storage directory sudah diset
- [ ] Database credentials sudah diverifikasi

### Monitoring
- [ ] Docker container status: all healthy
- [ ] Disk space: df -h (verify > 20% free)
- [ ] RAM usage: docker stats (verify < 85% used)

### Backup
- [ ] Backup script sudah dibuat
- [ ] Backup schedule sudah diset
- [ ] Test backup restoration pernah dilakukan
```

### 7.3 Best Practices

1. **Jangan pernah edit file di dalam container secara langsung**
   - Gunakan volume mount
   - Edit config via Web UI atau LDAP
   - Kalau edit manual di container → hilang saat container di-recreate

2. **Selalu set proper ownership sebelum start**
   ```bash
   sudo chown -R 999:999 /opt/dcm4chee-arc/storage
   sudo chown -R 700 /opt/dcm4chee-arc/ldap
   sudo chown -R 700 /opt/dcm4chee-arc/db
   ```

3. **Monitor disk space**
   - DICOM files makan storage cepat
   - Set alert di 80% kapasitas
   - Rencanakan storage expansion sebelum penuh

4. **Gunakan named volumes dengan prefix**
   - `dcm4chee_ldap_data` bukan `ldap_data`
   - Agar tidak conflict dengan project lain

5. **Ubah default password**
   - LDAP admin: `changeit` → password kuat
   - PostgreSQL: `pacs` → password kuat
   - Web UI root: `changeit` → password kuat

---

## Ringkasan Level Basic

```
Level Basic Coverage:
├── Konsep DICOM (data model, services, AE title)    ✅
├── Arsitektur DCM4CHEE (WildFly, LDAP, PostgreSQL)  ✅
├── Instalasi Docker Compose                         ✅
├── Hands-on Exercises (4 exercises)                ✅
├── Arsitektur jaringan & DICOM flow                ✅
├── Studi kasus RS kecil                            ✅
├── Troubleshooting & best practices                ✅
└── Security: Ubah default passwords                ✅
```

**Next Steps — Level Intermediate:**
1. Konfigurasi PACS lanjutan (parameter sets, archive configs)
2. Modality Worklist (MWL) integration dengan HIS
3. Query/Retrieve server configuration untuk DICOM clients
4. Database PostgreSQL optimization untuk production
5. HIS/EMR integration (HL7 ORM messages)
6. Basic monitoring dan alerting
7. Studi kasus RS Menengah (multi-modality, HIS integration)