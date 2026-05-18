# LEVEL 2: INTERMEDIATE — Integrasi & Konfigurasi Lanjutan DCM4CHEE

## Prasyarat

Level ini melanjutkan dari Level Basic. Sebelum memulai, pastikan Anda sudah:
- [ ] DCM4CHEE Archive 5.x sudah running via Docker Compose
- [ ] Web UI accessible di `http://server:8080/dcm4chee-arc/ui2`
- [ ] C-ECHO test berhasil dari test client
- [ ] Mampu kirim dan retrieve DICOM file via STOW/QIDO

## Tujuan Pembelajaran

Setelah menyelesaikan level ini, Anda akan mampu:
1. Mengkonfigurasi PACS parameter sets dan archive configurations
2. Setup Modality Worklist (MWL) dari HL7 order messages
3. Mengkonfigurasi Query/Retrieve SCP untuk berbagai DICOM clients
4. Mengintegrasikan DCM4CHEE dengan sistem HIS/EMR RS
5. Setup database PostgreSQL untuk production load
6. Implementasikan basic monitoring dan logging

---

## Modul 1: Konfigurasi PACS Parameter Sets

### 1.1 Archive Device Configuration (Web UI)

Buka **Configuration → Devices → DCM4CHEE**. Di halaman ini Anda mengkonfigurasi device-level parameters.

**Parameter kritis yang harus di-set:**

| Parameter | Default | Rekomendasi | Penjelasan |
|-----------|---------|-------------|-----------|
| `Device Name` | DCM4CHEE | Sesuaikan naming RS | Nama device di LDAP |
| `Station Name` | - | Nama RS/stasiun | Identitas visual di DICOM |
| `Installed` | - | PRESENT | Tells other systems this device is online |
| `Association Control` | AUTO | AUTO | Control association acceptance |
| ` Maximum Storage` | 0 | 500GB (RS kecil) | Max storage sebelum reject |
| `hl7SendApp Name` | - | DCM4CHEE | Sending HL7 application |
| `hl7ReceivePort` | 2575 | 2575 | HL7 ADT receiver port |
| `dcmWebApp Name` | DCM4CHEE | DCM4CHEE | Web app identifier |

**Mengapa penting?**
- `Installed: PRESENT` → client lain bisa deteksi apakah archive online via DICOM C-GET/C-MOVE
- `Maximum Storage` → protect dari storage penuh dengan auto-reject setelah threshold
- HL7 ports → MWL dan ADT integration bergantung pada port ini

### 1.2 Network AE Configuration (AE Titles)

Setiap modality dan client yang mau connect ke DCM4CHEE harus punya AE entry.

**Konfigurasi via Web UI: Configuration → Network → Network AE**

**Tambahkan AE baru untuk modality:**

```
AE Title: [CT_SIEMENS]
Host Name: [ct-siemens.local / IP address]
Port: [11112]
Description: [CT Siemens Emotion 16]
```

**Transfer Syntax yang harus didukung:**

```
Mandatory (wajib):
✓ 1.2.840.10008.1.2   Implicit VR Little Endian (default DICOM)
✓ 1.2.840.10008.1.2.1 Explicit VR Little Endian

Recommended (disarankan untuk kompresi):
✓ 1.2.840.10008.1.2.4.50 JPEG Baseline Process 1
✓ 1.2.840.10008.1.2.4.57 JPEG Lossless
✓ 1.2.840.10008.1.2.4.70 JPEG Lossless Selection Value 1

Untuk Transfer (retrieve):
✓ 1.2.840.10008.1.2   Implicit VR Little Endian
✓ 1.2.840.10008.1.2.1 Explicit VR Little Endian
```

**Konfigurasi lain per AE (tab advanced):**

| Setting | Penjelasan | Contoh |
|---------|-----------|--------|
| `Other AE Titles` | Daftar AE yang boleh connect ke AE ini | `CT_SIEMENS\MRI_GE` |
| `Max Associations` | Max simultaneous associations | 10 |
| `Max PDU Length` | Max Protocol Data Unit (buffer size) | 1048576 (1MB) |
| `Supported TS` | Transfer syntaxes yang didukung | (isi sesuai modality) |
| `Preferred TS` | Transfer syntax prioritas untuk retrieve | Implicit LE |
| `Fuzzy Algorithm` | Algoritma fuzzy matching untuk Patient Name | Metaphone |

**Konfigurasi C-MOVE/C-GET Receiver (Retrieve):**

Jika modality/client mau retrieve gambar dari archive, mereka perlu AE title sendiri di-config. Tapi retrieve tidak harus listing di "Other AE Titles" di device DCM4CHEE — cukup AE title mereka sudah terdaftar dan DCM4CHEE bisa connect back ke mereka.

**Ceklis konfigurasi AE per modality:**

```markdown
## Modality AE Configuration Checklist

Per modality yang mau connect:
- [ ] AE Title diverifikasi (sesuai vendor config modality)
- [ ] Host/IP sudah diset (atau Hostname resolvable)
- [ ] Port sudah diset (default: 11112)
- [ ] Accepted Transfer Syntaxes sesuai kemampuan modality
- [ ] AE title terdaftar di Other AE Titles (DCM4CHEE device)
- [ ] C-ECHO test berhasil dari modality
- [ ] C-STORE test berhasil (modality kirim gambar)
```

### 1.3 Storage Configuration (Web UI)

**Configuration → Devices → DCM4CHEE → Storage:**

DCM4CHEE mendukung multiple storage. Pada setup dasar, storage default adalah filesystem yang di-mount ke `/storage`.

**Storage properties kritis:**

| Property | Default | Penjelasan |
|----------|---------|-----------|
| `Storage ID` | FS1 | Identifier storage |
| `URI` | file:///storage/fs1 | Path atau S3 URI |
| `availability` | ONLINE | ONLINE/NEARLINE/OFFLINE |
| `quota` | 0 (unlimited) | Max storage dalam bytes |
| `pathFormat` | `{now,date,yyyy/MM/dd}/{0020000D,hash}/{0020000E,hash}/{00080018,hash}` | Layout penyimpanan |
| `Digest Algorithm` | MD5 | Untuk integrity verification |
| `objectExists` | NOOP | Policy saat object sudah ada |

**pathFormat explained:**
```
{now,date,yyyy/MM/dd}  → 2025/05/18 (tanggal receive)
{0020000D,hash}        → hash dari Study Instance UID
{0020000E,hash}        → hash dari Series Instance UID
{00080018,hash}        → hash dari SOP Instance UID

Contoh hasil:
/storage/2025/05/18/a1b2c3d4/a5b6c7d8/e9f0a1b2c3.dcm
```

**Storage tiers (menurut ukuran RS):**

| Tier | Availability | Use Case | Storage Type |
|------|-------------|-----------|-------------|
| Tier 1 | ONLINE | Active/recent studies | Fast SSD/NVMe |
| Tier 2 | NEARLINE | Studies > 30 days | NAS/HDD |
| Tier 3 | OFFLINE | Archive/digital backup | Tape/S3 Glacier |

**Konfigurasi untuk RS kecil-menengah (single tier):**

```yaml
# Environment variable di docker-compose.yml
services:
  arc:
    environment:
      ARCHIVE_STORAGE_DIR: /storage/archive
```

**Konfigurasi storage via Web UI:**

1. Configuration → Devices → DCM4CHEE → Storage
2. Klik storage dengan Storage ID `FS1`
3. Edit `URI` path jika perlu
4. Set `quota` sesuai kebutuhan
5. Klik **Update**

---

## Modul 2: Modality Worklist (MWL) Integration

### 2.1 Konsep MWL

**Modality Worklist** adalah daftar "pekerjaan" yang harus dilakukan oleh modality. Daftar ini berasal dari sistem **HIS/RIS** (radiology information system) dalam bentuk HL7 ORM messages, yang kemudian dikonversi menjadi DICOM MWL entries oleh DCM4CHEE.

**Workflow MWL:**

```
HIS / RIS (Radiologist Order)
      │
      │ HL7 ORM^O01 Message
      │ Port 2575 (HL7 MLLP)
      ▼
DCM4CHEE (HL7 Service)
      │ Parse HL7 message
      │ XSLT transform: orm2dcm.xsl
      ▼
MWL Database (mwl_items table)
      │
      │ C-FIND MWL (query dari Modality)
      ▼
Modality Worklist SCP
      │ Response dengan worklist entries
      ▼
Modality (Technologist sees scheduled procedure)
```

**MWL Entry fields yang dipetakan dari HL7:**

| DICOM MWL Field | Source HL7 Field |
|----------------|-----------------|
| Patient ID | PID.3.1 |
| Patient Name | PID.5.1 |
| Patient Birth Date | PID.7 |
| Patient Sex | PID.8 |
| Accession Number | OBR.3 (atau ORC.2) |
| Requested Procedure ID | OBR.3 |
| Requested Procedure Description | OBR.4 |
| Scheduled Procedure Step ID | OBR.3 |
| Scheduled Procedure Step Description | OBR.4 |
| Scheduled Station AE Title | OBR.24 |
| Scheduled Station Name | OBR.24 |
| Scheduled Modality | OBR.24 |
| Scheduled Date/Time | OBR.7 |

### 2.2 Setup HL7 Receiver untuk MWL

**Langkah 1: Konfigurasi HL7 Service via Web UI**

1. Buka **Configuration → Devices → DCM4CHEE → HL7 Service (ORM)**
2. Aktifkan service:

| Setting | Value | Penjelasan |
|---------|-------|-----------|
| `Enable` | true | Aktifkan HL7 ORM service |
| `Called Application` | DCM4CHEE | Called app di HL7 message |
| `Message Types` | ORM^O01 | HL7 message type untuk order |
| `Default Modality` | OT | Fallback modality kalau tidak ada di HL7 |
| `Default Station AET` | DCM4CHEE | Fallback station |

3. Klik **Update**

**Langkah 2: Konfigurasi MWL SCP**

1. Buka **Configuration → Devices → DCM4CHEE → MWL SCP**
2. Konfigurasi:

| Setting | Value |
|---------|-------|
| `Called AE Titles` | DCM4EMWL` atau `DCM4CHEE` |
| `Calling AE Titles` | `ANY` (accept any) |

3. Klik **Update**

**Langkah 3: Configure Stylesheet (Advanced)**

Jika HIS mengirim format HL7 yang berbeda, Anda perlu customize XSLT stylesheet.

```bash
# Download default stylesheet dari container
docker cp dcm4chee-arc:/opt/wildfly/standalone/configuration/conf/dcm4chee-hl7/orm2dcm.xsl \
  ./orm2dcm_custom.xsl

# Edit sesuai format HIS Anda
# Contoh: mapping field non-standard
# Lalu upload ke container
docker cp ./orm2dcm_custom.xsl dcm4chee-arc:/opt/wildfly/standalone/conf/dcm4chee-hl7/orm2dcm_custom.xsl
```

**Via Web UI:**

1. Configuration → Devices → DCM4CHEE → HL7 Service (ORM)
2. Ganti `Stylesheet` dari `orm2dcm.xsl` ke `orm2dcm_custom.xsl`
3. Klik **Update**

### 2.3 Test MWL Integration

**Test kirim HL7 ORM message:**

```bash
# Kirim HL7 ORM message via MLLP
# Port 2762 = HL7 ORM receiver, 2575 = HL7 ADT receiver

# Contoh HL7 ORM message minimal
HL7_MESSAGE='MSH|^~\&|HIS|DCM4CHEE|DCM4CHEE|DCM4CHEE|202501181200||ORM^O01|MSG001|P|2.5
MSA|AA|MSG001
PID|1||P12345^^^DCM4CHEE||DOE^JOHN||19850515|M
ORC|NW|PROCNUM123|||SC
OBR|1|PROCNUM123||CT^CT SCAN THORAX|||202501181400|||RAD01|||CT_SIEMENS'

# Kirim via netcat (port 2762)
echo -ne "$HL7_MESSAGE\0" | nc -w 5 localhost 2762
```

**Verifikasi via Web UI:**

1. Buka **http://server:8080/dcm4chee-arc/ui2**
2. Menu: **Queue** → **MWL**
3. Cari entries dengan Accession Number `PROCNUM123`
4. Verifikasi:
   - Patient ID: P12345
   - Patient Name: DOE^JOHN
   - Modality: CT
   - Station: CT_SIEMENS

**Test retrieve MWL dari modality:**

```bash
# Query MWL via DCM4CHEE
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems"

# Query MWL by Patient ID
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems?PatientID=P12345"
```

---

## Modul 3: HIS/EMR Integration (RS Indonesia)

### 3.1 Integration Architecture

Di RS Indonesia, DCM4CHEE biasanya diintegrasikan dengan:

```
┌─────────────────────────────────────────────────────────────────┐
│                    INTEGRASI SISTEM RS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌────────────┐     HL7 ORM     ┌─────────────────┐          │
│   │    HIS     │─────────────────►│  HL7 Receiver   │          │
│   │(SIMRS/     │  Port 2575/2762  │   (DCM4CHEE)    │          │
│   │Bahmni/etc) │                 │  Modality       │          │
│   └────────────┘                 │  Worklist SCP   │          │
│                                  └────────┬────────┘          │
│                                           │                    │
│                                  C-FIND MWL│                    │
│                                           ▼                    │
│                                  ┌────────────────┐           │
│                                  │    Modality     │           │
│                                  │(CT, MRI, US, CR)│           │
│                                  └────────┬────────┘           │
│                                           │                    │
│                                  C-STORE  │                    │
│                                           ▼                    │
│                                  ┌────────────────┐           │
│                                  │  Storage SCP   │           │
│                                  │  (DCM4CHEE)    │           │
│                                  └────────┬────────┘           │
│                                           │                    │
│                                  ┌────────▼────────┐           │
│                                  │   Web Viewer   │           │
│                                  │(OHIF/Weasis)   │           │
│                                  └────────────────┘           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 HL7 Message Types yang Dipakai

| Message Type | Fungsi | Port |
|-------------|--------|------|
| `ORM^O01` | Radiology Order | 2762 |
| `ADT^A01` | Patient Admission | 2575 |
| `ADT^A08` | Patient Update (demographics change) | 2575 |
| `ADT^A10` | Patient Arrival | 2575 |
| `ADT^A40` | Patient Merge (ID change) | 2575 |
| `ORM^O01` + `ORC|CA` | Order Cancel | 2762 |

### 3.3 Konfigurasi HL7 Receiver (ADT) via Web UI

**Untuk ADT messages (patient arrival, admission):**

1. Buka **Configuration → Devices → DCM4CHEE → HL7 Service (ADT)**
2. Konfigurasi:

| Setting | Value |
|---------|-------|
| `Enable` | true |
| `Called Application` | DCM4CHEE |
| `Called Facility` | DCM4CHEE |

3. Klik **Update**

### 3.4 Konfigurasi HIS/EMR (Studi Kasus: SIMRS Ganesha)**

Jika RS menggunakan **SIMRS Ganesha** atau **Bahmni**:

```bash
# HIS perlu kirim HL7 ke DCM4CHEE
# Konfigurasi di HIS:

# 1. Set HL7 server address
HL7_HOST=localhost
HL7_PORT=2762  # untuk ORM orders
HL7_PORT_ADT=2575  # untuk ADT messages

# 2. Format HL7 sesuai standar
# MSH segment harus include:
# - Sending Application (HIS)
# - Sending Facility (nama RS)
# - Receiving Application (DCM4CHEE)
# - Receiving Facility (DCM4CHEE)

# 3. PID segment mapping:
# - PID.3 = Patient ID (dengan namespace)
# - PID.5 = Patient Name (format LAST^FIRST)
# - PID.7 = Birth Date (YYYYMMDD)
# - PID.8 = Sex (M/F/O)

# 4. OBR segment mapping:
# - OBR.3 = Accession Number / Procedure ID
# - OBR.4 = Procedure Code (CT, MRI, US, dll)
# - OBR.7 = Scheduled Date/Time (YYYYMMDDHHMM)
# - OBR.24 = Scheduled Station (AE title modality)
```

**Integration dengan Bahmni (contoh spesifik):**

Bahmni menggunakan `pacs-integration` service yang menjembatani HIS dengan DCM4CHEE.

```bash
# Konfigurasi di Bahmni:
# File: /opt/bahmni-config/openmrs/apps/radiology/concepts.json
# Sudah include mapping radiology procedure codes

# Setup DCM4CHEE HL7 stylesheet untuk Bahmni
# 1. Akses JMX console
# http://server:9990/jmx-console

# 2. Navigate to:
# service=HL7Service,type=ORM under dcm4chee.archive

# 3. Set Stylesheet = orm2dcm_bahmni.xsl

# 4. Verify MWL muncul di modality
```

### 3.5 MPPS (Modality Procedure Step) Integration

**MPPS** memungkinkan modality mengirim "status procedure" kembali ke archive:
- `N-CREATE` → Procedure Started
- `N-SET` → Procedure In Progress
- `N-SET` → Procedure Completed/Discontinued

**Setup:**

1. Buka **Configuration → Devices → DCM4CHEE → MPPS SCP**
2. Aktifkan:

| Setting | Value |
|---------|-------|
| `Enable` | true |
| `Called AE Titles` | DCM4CHEE |

3. Klik **Update**

**Konfigurasi di sisi modality (vendor):**

Modality perlu dikonfigurasi untuk kirim MPPS ke:
- AE Title: `DCM4CHEE`
- Host: `<dcm4chee-server-ip>`
- Port: `11112`

**Mengapa MPPS penting?**
- Update MWL item status otomatis (SCHEDULED → STARTED → COMPLETED)
- Radiologist tahu procedure sudah selesai dan bisa mulai reading
- Audit trail procedure completion

---

## Modul 4: Query/Retrieve SCP Configuration

### 4.1 Konsep QR SCP

**Query/Retrieve SCP** adalah service yang memungkinkan client (viewer, workstation, other PACS) untuk:
- **Query (C-FIND)**: Mencari patient/study/series/instance metadata
- **Retrieve (C-MOVE / C-GET)**: Mengambil gambar DICOM

**Dua tipe retrieve:**
- **C-MOVE**: Archive push ke AE tujuan (client harus terima koneksi inbound)
- **C-GET**: Archive pull (archive menarik dari dirinya sendiri)

**QR SCP vs QIDO-RS:**
- QR SCP: Protokol DICOM native (TCP/IP based)
- QIDO-RS: REST API over HTTP (lebih modern, firewall-friendly)

### 4.2 Konfigurasi QR SCP via Web UI

1. Buka **Configuration → Devices → DCM4CHEE → Query/Retrieve SCP**
2. Konfigurasi:

| Setting | Value | Penjelasan |
|---------|-------|-----------|
| `Enable` | true | Aktifkan service |
| `Called AE Titles` | DCM4CHEE | AE yang menerima query |
| `Default Retrieve AET` | DCM4CHEE | AE untuk retrieve |
| `Query Level` | Study/Series/Instance | Level query yang didukung |

3. **Konfigurasi Query Options:**

| Setting | Value | Penjelasan |
|---------|-------|-----------|
| `Fuzzy Algorithm` | Metaphone | Untuk patient name fuzzy matching |
| `Timezone` | Asia/Jakarta | Untuk date/time adjustment |
| `Relational Queries` | true | Mendukung relational queries |
| `Patient Root` | true | Mendukung Patient Root hierarki |
| `Study Root` | true | Mendukung Study Root hierarki |
| `Include Fields` | ALL | Return semua DICOM fields |

4. Klik **Update**

### 4.3 Configure Retrieve Destination (C-MOVE)**

C-MOVE membutuhkan "destination AE" tempat gambar akan dikirim.

**Setup destination AE via Web UI:**

1. Buka **Configuration → Network → Network AE**
2. Klik **Create**
3. Isi untuk viewer/workstation:

| Field | Value | Penjelasan |
|-------|-------|-----------|
| `AE Title` | OHIF_VIEWER | AE title client |
| `Host Name` | viewer-server.local | Host/IP viewer |
| `Port` | 8042 | Port viewer DICOM |

4. Klik **Save**

**Di sisi viewer/client:**
- Viewer juga perlu di-konfigurasi sebagai DICOM SCU (client)
- AE Title, Host, Port harus sesuai dengan config di atas

### 4.4 C-GET Configuration**

C-GET berbeda dari C-MOVE karena archive menarik data dari dirinya sendiri (tidak perlu koneksi outbound ke AE tujuan).

**Konfigurasi:**

1. Buka **Configuration → Devices → DCM4CHEE → Retrieve Manager**
2. Aktifkan **C-GET SCP**:

| Setting | Value |
|---------|-------|
| `Enable` | true |
| `Called AE Titles` | DCM4CHEE |

3. Klik **Update**

### 4.5 QIDO-RS (REST) Configuration**

QIDO-RS adalah REST API untuk query. Sudah aktif default.

**Endpoint:**

```
GET http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies
GET http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs/series
GET http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs/instances
GET http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems
```

**Query parameters:**

| Parameter | Contoh | Penjelasan |
|-----------|---------|-----------|
| `limit` | `limit=20` | Max results |
| `offset` | `offset=0` | Pagination offset |
| `PatientID` | `PatientID=P12345` | Filter by Patient ID |
| `PatientName` | `PatientName=JOHN` | Filter by name |
| `StudyDate` | `StudyDate=20250118` | Filter by date |
| `Modality` | `Modality=CT` | Filter by modality |
| `includefield` | `includefield=00081030` | Include specific DICOM tag |

---

## Modul 5: Database PostgreSQL Optimization

### 5.1 Database Connection Tuning

**Via docker-compose.yml environment variables:**

```yaml
services:
  arc:
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: sangat_rahasia_123
      # Connection pool settings
      POSTGRES_POOL_MIN: 5
      POSTGRES_POOL_MAX: 50
```

**Via Web UI — Connection Pool:**

1. Buka **http://server:9990** (WildFly Console)
2. Login: `root` / `changeit`
3. Navigate: **Configuration → Datasources → PacsDS**
4. Tune settings:

| Setting | Value (Small RS) | Value (Medium RS) |
|---------|------------------|-------------------|
| `Min Pool Size` | 5 | 10 |
| `Max Pool Size` | 50 | 100 |
| `Blocking Timeout` | 30000 (ms) | 60000 |
| `Idle Timeout` | 300000 (ms) | 600000 |

### 5.2 PostgreSQL Performance Tuning

**PostgreSQL config untuk production:**

```bash
# Edit postgresql.conf (mounted volume)
nano /opt/dcm4chee-arc/db/postgresql.conf

# Critical settings untuk DCM4CHEE:
```

```
# Memory settings
shared_buffers = 256MB          # 25% RAM
effective_cache_size = 768MB    # 75% RAM
work_mem = 16MB                 # Per connection
maintenance_work_mem = 128MB    # VACUUM, ANALYZE

# Write performance
wal_buffers = 16MB
checkpoint_completion_target = 0.9
max_wal_size = 1GB
min_wal_size = 80MB

# Connection settings
max_connections = 100

# Query optimization (CRITICAL untuk DCM4CHEE)
from_collapse_limit = 16
join_collapse_limit = 16

# Logging
log_statement = 'none'          # Matikan query logging untuk performance
log_min_duration_statement = 5000  # Log slow queries > 5 detik
```

**Apply changes:**

```bash
# Restart PostgreSQL container
docker compose restart db
```

**Mengapa `from_collapse_limit` dan `join_collapse_limit` penting?**
- DCM4CHEE query sering JOIN banyak tabel (patients + studies + series + instances)
- Default PostgreSQL = 8, yang menyebabkan sub-optimal query plan untuk query kompleks
- Set ke 16 untuk better query plans [(dcm4che Issue #4621)](https://github.com/dcm4che/dcm4chee-arc-light/issues/4621)

### 5.3 Monitoring Database Performance

```bash
# Cek database size
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT pg_size_pretty(pg_database_size('pacsdb'));"

# Cek number of records
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT 'patients' as tbl, count(*) FROM patients UNION ALL \
   SELECT 'studies', count(*) FROM studies UNION ALL \
   SELECT 'series', count(*) FROM series UNION ALL \
   SELECT 'instances', count(*) FROM instances;"

# Cek slow queries
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT query, calls, mean_time, total_time \
   FROM pg_stat_statements \
   ORDER BY mean_time DESC LIMIT 10;"

# Cek connection status
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT count(*) as active_connections, \
          state, \
          datname \
   FROM pg_stat_activity \
   GROUP BY state, datname;"
```

---

## Modul 6: Hands-On Exercises

### Exercise 1: Setup Multi-Modality AE Titles

**Tujuan:** Konfigurasi archive untuk menerima koneksi dari 3 modality berbeda (CT, MRI, USG).

**Langkah:**

1. Buka Web UI → Configuration → Network → Network AE
2. Buat 3 AE entries:

**AE 1: CT_SIEMENS**
```
AE Title: CT_SIEMENS
Host Name: 192.168.1.10
Port: 11112
Accepted TS: Implicit LE, Explicit LE, JPEG Baseline
```

**AE 2: MRI_GE**
```
AE Title: MRI_GE
Host Name: 192.168.1.11
Port: 11112
Accepted TS: Implicit LE, Explicit LE, JPEG Lossless
```

**AE 3: USG_PHILIPS**
```
AE Title: USG_PHILIPS
Host Name: 192.168.1.12
Port: 11112
Accepted TS: Implicit LE, Explicit LE
```

3. Buka Configuration → Devices → DCM4CHEE → Network Configuration
4. Di section **Other AE Titles**, tambahkan:
   ```
   CT_SIEMENS\MRI_GE\USG_PHILIPS
   ```
5. Klik Save

**Expected result:** Ketiga modality bisa C-ECHO dan C-STORE ke DCM4CHEE.

---

### Exercise 2: Setup MWL dari HL7 Orders

**Tujuan:** Kirim order dari HL7 simulator, verifikasi MWL entry dibuat di DCM4CHEE.

**Langkah:**

1. Buat HL7 simulator script:

```bash
cat > /tmp/send_hl7_order.sh << 'SCRIPT'
#!/bin/bash
HL7_HOST=localhost
HL7_PORT=2762

HL7_MSG='MSH|^~\&|SIMRS|SAMPLE_RS|DCM4CHEE|DCM4CHEE|202501181200||ORM^O01|MSG001|P|2.5
MSA|AA|MSG001
PID|1||P001^^^DCM4CHEE||BUDI^SETIAWAN||19880120|M
ORC|NW|ORD001|||SC
OBR|1|ORD001||CT^CT SCAN THORAX||202501181400|||CT_SIEMENS|||CT||||||||||||||RAD'

echo -ne "$HL7_MSG\0" | nc -w 10 $HL7_HOST $HL7_PORT
echo "HL7 Message sent"
SCRIPT

chmod +x /tmp/send_hl7_order.sh
/tmp/send_hl7_order.sh
```

2. Verifikasi MWL entry via Web UI:
   - Buka Queue → MWL
   - Cari accession number ORD001
   - Verifikasi Patient Name: BUDI^SETIAWAN
   - Verifikasi Modality: CT

3. Query MWL via REST API:

```bash
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems?PatientID=P001" | jq .
```

**Expected result:**
- MWL entry terbuat dengan data yang benar
- Status: SCHEDULED

---

### Exercise 3: Configure WADO-RS untuk Web Viewer Integration

**Tujuan:** Konfigurasi WADO-RS endpoint dan test retrieve gambar ke OHIF viewer.

**Langkah:**

1. Buka **Configuration → Devices → DCM4CHEE → Web Applications**
2. Edit **DCM4CHEE** web application:
   - Pastikan **WADO-RS** aktif
   - Pastikan **WADO-URI** aktif
   - Catat Base URL: `http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs`

3. Setup OHIF Viewer dengan DCM4CHEE:

```bash
# Buat ohif config untuk Docker development
cat > /tmp/ohif-dcm4chee.js << 'EOF'
window.config = {
  routerBasename: '/',
  extensions: [],
  modes: [],
  showStudyList: true,
  dataSources: [
    {
      namespace: '@ohif/extension-default.dataSourcesModule.dicomweb',
      sourceName: 'dicomweb',
      configuration: {
        friendlyName: 'DCM4CHEE Local',
        name: 'DCM4CHEE',
        wadoUriRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/wado',
        qidoRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
        wadoRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
        qidoSupportsIncludeField: true,
        supportsReject: true,
        imageRendering: 'wadors',
        thumbnailRendering: 'wadors',
        enableStudyLazyLoad: true,
        supportsFuzzyMatching: true,
        supportsWildcard: true,
      },
    },
  ],
  defaultDataSourceName: 'dicomweb',
};
EOF
```

4. Test WADO-RS retrieve:

```bash
# Ambil Study UID dari Exercise 2 (Level Basic)
STUDY_UID="1.2.840.113619.2.1.2411.005544122258901902019.0055542589019047"

# Retrieve study metadata via WADO-RS
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies/$STUDY_UID" \
  -H "Accept: application/dicom+json"

# Retrieve instance thumbnail (WADO-URI)
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/wado?requestType=WADO&studyUID=$STUDY_UID" \
  -o /tmp/study_rendered.jpg
```

**Expected result:** OHIF viewer bisa load dan display study dari DCM4CHEE.

---

### Exercise 4: Database Performance Benchmark

**Tujuan:** Measure query performance sebelum dan sesudah optimization.

**Langkah:**

```bash
# 1. Count records
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT count(*) as total_studies FROM studies;"

# 2. Run sample queries and measure time
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "EXPLAIN ANALYZE \
   SELECT s.study_iuid, p.pat_name, p.pat_birth_date, s.study_date, s.modality \
   FROM studies s \
   JOIN patients p ON s.pat_id = p.pat_id \
   LEFT JOIN series se ON s.study_iuid = se.study_iuid \
   WHERE s.study_date >= '2024-01-01' AND s.study_date <= '2024-12-31' \
   LIMIT 100;"

# 3. Cek index usage
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT indexname, idx_scan, idx_tup_read, idx_tup_fetch \
   FROM pg_stat_user_indexes \
   ORDER BY idx_scan DESC LIMIT 20;"

# 4. Cek table sizes
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) \
   FROM pg_stat_user_tables \
   ORDER BY pg_total_relation_size(relid) DESC LIMIT 10;"
```

**Expected result:** Query dengan JOIN 3+ tabel harus selesai < 1 detik untuk 1000 records.

---

## Modul 7: Studi Kasus — RS Menengah (Multi-Modality, HIS Integration)

### 7.1 Profile RS type ini

- **Modalitas:** 3-8 unit (CT, MRI, 2× CR, 2× USG, 1× XA)
- **Jumlah study/hari:** 100-300 study
- **Bed capacity:** 50-200 beds
- **IT Staff:** 2-3 orang, ada DBA
- **Integrasi:** HIS (SIMRS/Bahmni), EMR, LIS

### 7.2 Arsitektur untuk RS Menengah

```
┌────────────────────────────────────────────────────────────────┐
│                    RS MENENGAH (50-200 beds)                  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌────────────────────────────────────────────────────┐       │
│  │               HIS / EMR Server                      │       │
│  │     (SIMRS Ganesha / Bahmni / OpenMRS)            │       │
│  │     Port: 8080 (Web), 3306 (MySQL)                │       │
│  └──────────────────────┬─────────────────────────────┘       │
│                         │ HL7 ORM (2762) / ADT (2575)         │
│                         ▼                                     │
│  ┌────────────────────────────────────────────────────┐       │
│  │           DCM4CHEE Archive Cluster                  │       │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐           │       │
│  │  │   ARC1   │ │   ARC2   │ │   ARC3   │ (Opsional) │       │
│  │  │ (WildFly)│ │ (WildFly)│ │ (WildFly)│           │       │
│  │  └─────┬────┘ └─────┬────┘ └─────┬────┘           │       │
│  │        │           │           │                    │       │
│  │  ┌─────▼───────────▼───────────▼──────┐           │       │
│  │  │        PostgreSQL Cluster          │           │       │
│  │  │  Primary (master) + Replica       │           │       │
│  │  └───────────────────────────────────┘           │       │
│  └──────────────────────┬─────────────────────────────┘       │
│                         │                                     │
│  ┌──────────────────────┼─────────────────────────────┐      │
│  │                      │                              │      │
│  ▼                      ▼                              ▼      │
│ ┌──────────┐    ┌──────────┐    ┌──────────────────┐ │      │
│ │CT_SIEMENS│    │MRI_GE    │    │Radiologist       │ │      │
│ │Port:11112│    │Port:11112│    │Workstation (OHIF)│ │      │
│ └──────────┘    └──────────┘    │AE: OHIF_VIEWER   │ │      │
│                                  │Port: 8042        │ │      │
│ ┌──────────┐    ┌──────────┐     └──────────────────┘ │      │
│ │CR_1      │    │USG_1     │                           │      │
│ │Port:11112│    │Port:11112│                           │      │
│ └──────────┘    └──────────┘                           │      │
│                                                                 │
│  Storage: NAS (NAS-GBit, 4TB+, RAID5/6)                        │
│  Backup: Rsync ke offsite storage (Harian)                     │
└─────────────────────────────────────────────────────────────────┘
```

### 7.3 Konfigurasi Kritis untuk RS Menengah

**Konfigurasi untuk HIS Integration:**

```yaml
# docker-compose.yml (ARC service)
services:
  arc:
    environment:
      # PostgreSQL
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: sangat_rahasia_123

      # Storage
      ARCHIVE_STORAGE_DIR: /storage/archive

      # HL7 Ports
      HL7_ORM_PORT: 2762
      HL7_ADT_PORT: 2575

      # WildFly tuning
      WILDFLY_WAIT_FOR: ldap:389 db:5432
      WILDFLY_CHOWN: /storage

      # Heap sizing untuk RS menengah
      WILDFLY_JAVA_OPTS: "-Xms2g -Xmx4g -XX:+UseG1GC"

    volumes:
      - ./storage:/storage
      - ./wildfly:/opt/wildfly/standalone
```

**Konfigurasi Database (PostgreSQL):**

```bash
# postgresql.conf optimization
cat >> /opt/dcm4chee-arc/db/postgresql.conf << 'EOF'
# DCM4CHEE specific optimizations
from_collapse_limit = 16
join_collapse_limit = 16

# Connection pool
max_connections = 100

# Memory (sesuaikan dengan RAM available)
shared_buffers = 512MB
effective_cache_size = 1536MB
work_mem = 32MB
maintenance_work_mem = 256MB

# Write-ahead log
wal_buffers = 32MB
max_wal_size = 2GB
min_wal_size = 160MB
EOF

# Restart PostgreSQL
docker compose -f /opt/dcm4chee-arc/docker-compose.yml restart db
```

**Konfigurasi NAS Storage:**

```bash
# Mount NAS ke /opt/dcm4chee-arc/storage
# Mount via NFS atau CIFS

# Via CIFS ( Samba ):
sudo mount -t cifs //nas-server.local/dcm4chee-archive \
  /opt/dcm4chee-arc/storage \
  -o username=pacs,password=rahasia,uid=999,gid=999,file_mode=0640,dir_mode=0750

# Add to /etc/fstab untuk auto-mount
//nas-server.local/dcm4chee-archive /opt/dcm4chee-arc/storage \
  cifs username=pacs,password=rahasia,uid=999,gid=999,file_mode=0640,dir_mode=0750,_netdev 0 0
```

### 7.4 Monitoring & Alerting Setup

```bash
# Setup monitoring script
cat > /opt/dcm4chee-arc/monitor.sh << 'MON'
#!/bin/bash
ALERT_EMAIL="admin@rumahsakit.com"
DCM_DIR="/opt/dcm4chee-arc"

# Check container health
for svc in ldap db arc; do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' dcm4chee-$svc 2>/dev/null)
  if [ "$STATUS" != "healthy" ]; then
    echo "ALERT: dcm4chee-$svc is $STATUS" | mail -s "DCM4CHEE Alert" $ALERT_EMAIL
  fi
done

# Check disk space
DF_OUTPUT=$(df -h $DCM_DIR/storage | tail -1)
USAGE=$(echo $DF_OUTPUT | awk '{print $5}' | tr -d '%')
if [ "$USAGE" -gt 80 ]; then
  echo "ALERT: Storage usage at ${USAGE}%" | mail -s "Storage Alert" $ALERT_EMAIL
fi

# Check database size
DB_SIZE=$(docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT pg_size_pretty(pg_database_size('pacsdb'));" -t | tr -d ' ')
echo "Database size: $DB_SIZE"

# Check error logs last hour
ERROR_COUNT=$(docker logs dcm4chee-arc --since 1h 2>&1 | grep -ci "ERROR\|SEVERE" || echo 0)
if [ "$ERROR_COUNT" -gt 10 ]; then
  echo "ALERT: $ERROR_COUNT errors in last hour" | mail -s "DCM4CHEE Error Alert" $ALERT_EMAIL
fi
MON

chmod +x /opt/dcm4chee-arc/monitor.sh

# Schedule monitoring (every 15 minutes)
(crontab -l 2>/dev/null | grep -v monitor.sh; echo "*/15 * * * * /opt/dcm4chee-arc/monitor.sh") | crontab -
```

### 7.5 Lessons Learned RS Menengah

| Issue | Cause | Solution |
|-------|-------|---------|
| MWL tidak tampil di modality | HL7 stylesheet tidak sesuai format HIS | Custom orm2dcm.xsl sesuai HIS vendor |
| Retrieve slow (> 30 detik) | HDD bottleneck, large study | SSD tier untuk active storage, compress older studies |
| HIS sering disconnect HL7 | HIS tidak handle MLLP dengan benar | Install HL7 proxy/gateway, retry logic di HIS |
| Database backup gagal | tablespace penuh | Automated backup + monitoring |
| Radiologist complaint: image not found | C-MOVE destination salah | Verifikasi AE title di viewer, test C-MOVE |

---

## Modul 8: Troubleshooting & Best Practices Intermediate

### 8.1 MWL Issues

**MWL tidak terbuat dari HL7 ORM:**

```bash
# 1. Cek HL7 receiver aktif
docker exec dcm4chee-arc netstat -tlnp | grep 2762

# 2. Cek HL7 message received
docker logs dcm4chee-arc --since 5m | grep -i "HL7\|ORM\|2762"

# 3. Test kirim HL7 manual
nc -w 5 localhost 2762 < /tmp/test_hl7.txt

# 4. Cek MWL items di DB
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT * FROM mwl_items ORDER BY created_time DESC LIMIT 5;"
```

### 8.2 Performance Issues

**Web UI slow:**

```bash
# 1. Cek JVM heap usage
docker exec dcm4chee-arc /opt/wildfly/bin/jboss-cli.sh \
  -c "/core-service=platform-mbean/type=memory:read-memory-usage"

# 2. Increase heap
docker compose edit arc
# Tambahkan: WILDFLY_JAVA_OPTS: "-Xms2g -Xmx4g"

# 3. Cek PostgreSQL connections
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT count(*) FROM pg_stat_activity WHERE datname='pacsdb';"

# 4. Enable query logging sementara untuk debug
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER DATABASE pacsdb SET log_statement = 'all';"
```

### 8.3 HIS Integration Issues

**HL7 message rejected:**

```bash
# 1. Cek MSH segment format
# HL7 message harus punya field delimiter yang benar
# MSH|^~\&|SENDING_APP|SENDING_FAC|RECV_APP|RECEIVING_FAC|...

# 2. Cek message type
# DCM4CHEE default: ORM^O01, ADT^A01, A08, A10, A40

# 3. Validasi HL7 message dengan tool
# Download HL7Soup atau use online validator
```

---

## Ringkasan Level Intermediate

```
Level Intermediate Coverage:
├── PACS Parameter & Archive Configuration     ✅
├── Modality Worklist (MWL) Setup              ✅
├── HIS/EMR Integration (HL7 ORM/ADT)          ✅
├── QR SCP Configuration (C-MOVE/C-GET)        ✅
├── QIDO-RS REST API Configuration             ✅
├── PostgreSQL Optimization (4GB+ RAM)          ✅
├── Hands-on Exercises (4 exercises)            ✅
├── Studi kasus RS Menengah                     ✅
├── Monitoring & Alerting Setup                 ✅
└── HIS Integration troubleshooting             ✅
```

**Next Steps — Level Advanced:**
1. Security: TLS/SSL, encryption at-rest, Keycloak SSO
2. Backup & Disaster Recovery (snapshot, offsite, failover)
3. Clustering & High Availability untuk RS besar
4. Performance tuning lanjutan (index optimization, caching)
5. Advanced monitoring dengan Elastic Stack
6. Compliance: HIPAA considerations, audit trail
7. Migration strategy dari PACS lama
8. Studi kasus RS Rujukan (HA cluster, disaster recovery)