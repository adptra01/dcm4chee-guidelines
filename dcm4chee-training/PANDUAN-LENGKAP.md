# Panduan Lengkap dcm4chee-arc dengan Docker

Panduan praktis instalasi, konfigurasi, troubleshooting, dan penggunaan REST API
dcm4chee-arc-light menggunakan Docker Compose.

**Daftar Isi:**
1. [Persiapan](#1-persiapan)
2. [Instalasi Docker Compose](#2-instalasi-docker-compose)
3. [Konfigurasi Penting](#3-konfigurasi-penting)
4. [Menjalankan Services](#4-menjalankan-services)
5. [Mengirim DICOM](#5-mengirim-dicom)
6. [REST API JSON](#6-rest-api-json)
7. [Troubleshooting (Error yang Sering Muncul)](#7-troubleshooting)
8. [Daftar URL Penting](#8-daftar-url-penting)
9. [Referensi](#9-referensi)

---

## 1. Persiapan

### 1.1. Prasyarat

- Docker Engine (versi 20+)
- Docker Compose (versi 2+)
- Java 17+ (hanya untuk tools DICOM via command line)
- DNS atau hostname yang bisa di-resolve (atau cukup IP local)

### 1.2. Port yang Digunakan

| Port | Service | Fungsi |
|------|---------|--------|
| 389, 636 | OpenLDAP | Autentikasi & konfigurasi |
| 3306 | MariaDB | Database Keycloak |
| 5432 | PostgreSQL | Database Archive |
| 8080 | Wildfly (HTTP) | Web UI & REST API |
| 8443 | Wildfly (HTTPS) | Web UI & REST API (secured) |
| 8843 | Keycloak | Authentication server |
| 11112 | DICOM | C-STORE SCP (terima DICOM) |
| 9990, 9993 | Wildfly Admin | Admin console |
| 2762, 2575, 12575 | DICOM TLS / HL7 | Protokol tambahan |

---

## 2. Instalasi Docker Compose

### 2.1. File docker-compose.yml

Buat file `docker-compose.yml` dengan isi berikut.
> **Penjelasan:** File ini mendefinisikan 5 service: OpenLDAP, MariaDB, Keycloak,
> PostgreSQL, dan Archive (dcm4chee-arc).

```yaml
version: "3"
services:
  ldap:
    image: dcm4che/slapd-dcm4chee:2.6.10-34.3
    logging:
      driver: json-file
      options:
        max-size: "10m"
    ports:
      - "389:389"
      - "636:636"
    environment:
      STORAGE_DIR: /storage/fs1
    volumes:
      - /var/local/dcm4chee-arc/ldap:/var/lib/openldap/openldap-data
      - /var/local/dcm4chee-arc/slapd.d:/etc/openldap/slapd.d

  mariadb:
    image: mariadb:10.11.4
    logging:
      driver: json-file
      options:
        max-size: "10m"
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: keycloak
      MYSQL_USER: keycloak
      MYSQL_PASSWORD: keycloak
    volumes:
      - /etc/localtime:/etc/localtime:ro
      - /etc/timezone:/etc/timezone:ro
      - /var/local/dcm4chee-arc/mysql:/var/lib/mysql

  keycloak:
    build:
      context: .
      dockerfile: Dockerfile.keycloak
    logging:
      driver: json-file
      options:
        max-size: "10m"
    ports:
      - "8843:8443"
    environment:
      KC_HTTPS_PORT: 8443
      KC_HOSTNAME: https://192.168.2.220:8843
      KC_HOSTNAME_BACKCHANNEL_DYNAMIC: 'true'
      KC_BOOTSTRAP_ADMIN_USERNAME: admin
      KC_BOOTSTRAP_ADMIN_PASSWORD: changeit
      DB_VENDOR: MARIADB
      DB_HOST: mariadb
      DB_PORT: 3306
      DB_DATABASE: keycloak
      DB_USER: keycloak
      DB_PASSWORD: keycloak
      KC_PROXY_HEADERS: xforwarded
      KC_LOG: file
      ARCHIVE_HOST: 192.168.2.220
      KEYCLOAK_WAIT_FOR: ldap:389 mariadb:3306
      KC_HTTP_ENABLED: 'true'
      KC_STRICT_HTTPS: 'false'
    depends_on:
      - ldap
      - mariadb
    volumes:
      - /etc/localtime:/etc/localtime:ro
      - /etc/timezone:/etc/timezone:ro
      - /var/local/dcm4chee-arc/keycloak:/opt/keycloak/data

  db:
    image: dcm4che/postgres-dcm4chee:17.4-34
    logging:
      driver: json-file
      options:
        max-size: "10m"
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: pacs
    volumes:
      - /etc/localtime:/etc/localtime:ro
      - /etc/timezone:/etc/timezone:ro
      - /var/local/dcm4chee-arc/db:/var/lib/postgresql/data

  arc:
    image: dcm4che/dcm4chee-arc-psql:5.34.3-secure
    ports:
      - "8080:8080"
      - "8443:8443"
      - "9990:9990"
      - "9993:9993"
      - "11112:11112"
      - "2762:2762"
      - "2575:2575"
      - "12575:12575"
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: pacs
      AUTH_SERVER_URL: https://keycloak:8443
      UI_AUTH_SERVER_URL: https://192.168.2.220:8843
      WILDFLY_CHOWN: /storage
      WILDFLY_WAIT_FOR: ldap:389 db:5432 keycloak:8443
    depends_on:
      - ldap
      - keycloak
      - db
    volumes:
      - /etc/localtime:/etc/localtime:ro
      - /etc/timezone:/etc/timezone:ro
      - /var/local/dcm4chee-arc/wildfly:/opt/wildfly/standalone
      - /var/local/dcm4chee-arc/storage:/storage
```

### 2.2. File Dockerfile.keycloak

Buat file `Dockerfile.keycloak` — file ini diperlukan karena image
dcm4che/keycloak membutuhkan utilitas `cmp` (dari package `diffutils`).
Tanpa ini, Keycloak akan crash dengan error `cmp: command not found`.

```dockerfile
FROM dcm4che/keycloak:26.0.6
RUN yum install -y diffutils && yum clean all && rm -rf /var/cache/yum
```

> **Mengapa perlu?** Entrypoint script Keycloak menggunakan `cmp` untuk
> membandingkan file konfigurasi. Image official `dcm4che/keycloak:26.0.6`
> tidak menyertakan `diffutils`, sehingga tanpa Dockerfile ini Keycloak
> akan langsung Exit Code 1.

---

## 3. Konfigurasi Penting

### 3.1. Hostname/IP Server

Ganti semua `192.168.2.220` dengan IP atau hostname server lo di 3 tempat:

| Service | Variable | Contoh |
|---------|----------|--------|
| keycloak | `KC_HOSTNAME` | `https://192.168.2.220:8843` |
| keycloak | `ARCHIVE_HOST` | `192.168.2.220` |
| arc | `UI_AUTH_SERVER_URL` | `https://192.168.2.220:8843` |

#### Pilihan Hostname:

| Skenario | Nilai | Keterangan |
|----------|-------|------------|
| Akses dari **mesin yang sama** | `localhost` | Stabil, ganti WiFi tetap jalan |
| Akses dari **LAN (satu jaringan)** | `192.168.2.220` | Ganti IP jika DHCP berubah |
| Akses via **internet (public)** | `pacs.domain.com` | Butuh domain, port forwarding, SSL |

### 3.2. Port Internal Keycloak

Keycloak menggunakan port internal **8443** (bukan 8843). Mapping:
- `"8843:8443"` → host buka port 8843, diteruskan ke port 8443 di container
- `KC_HTTPS_PORT: 8443` → Keycloak listen di port 8443

Penyesuaian di service `arc`:
- `AUTH_SERVER_URL: https://keycloak:8443` → komunikasi internal
- `WILDFLY_WAIT_FOR: ... keycloak:8443` → tunggu Keycloak di port 8443

### 3.3. SSL Self-Signed Certificate

Karena menggunakan IP (bukan domain) dan sertifikat self-signed, perlu
menambahkan:

```yaml
KC_HTTP_ENABLED: 'true'
KC_STRICT_HTTPS: 'false'
```

Ini mengizinkan HTTP dan mematikan strict HTTPS validation.

### 3.4. Variabel Database Keycloak

Ada 2 format yang didukung:

| Format | Variabel |
|--------|----------|
| **Standard wrapper dcm4che** (disarankan) | `DB_VENDOR`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USER`, `DB_PASSWORD` |
| Quarkus native | `KC_DB`, `KC_DB_URL_HOST`, `KC_DB_URL_DATABASE`, `KC_DB_USERNAME`, `KC_DB_PASSWORD` |

Gunakan format **standard wrapper** karena lebih stabil dengan entrypoint
script dcm4che.

---

## 4. Menjalankan Services

### 4.1. Jalankan Semua Container

```bash
# Pertama kali (build image keycloak + download images)
docker compose -p dcm4chee up -d

# Output yang diharapkan:
# Creating dcm4chee_ldap_1      ... done
# Creating dcm4chee_mariadb_1   ... done
# Creating dcm4chee_db_1        ... done
# Creating dcm4chee_keycloak_1  ... done
# Creating dcm4chee_arc_1        ... done
```

### 4.2. Perintah Penting

```bash
# Lihat status container
docker ps

# Lihat log service tertentu
docker logs dcm4chee-keycloak-1 --tail 50
docker logs dcm4chee-arc-1 --tail 50

# Stop semua container
docker compose -p dcm4chee down

# Start ulang
docker compose -p dcm4chee start

# Hapus + start ulang (bersih)
docker compose -p dcm4chee down
docker compose -p dcm4chee up -d

# Rebuild image keycloak jika ada perubahan Dockerfile
docker compose -p dcm4chee build keycloak
docker compose -p dcm4chee up -d
```

### 4.3. Reset Keycloak Cache (Jika Gagal Start)

Jika Keycloak crash setelah perubahan konfigurasi:

```bash
docker compose -p dcm4chee down
sudo rm -rf /var/local/dcm4chee-arc/keycloak/*
docker compose -p dcm4chee up -d
```

> **Penting:** Folder `/var/local/dcm4chee-arc/keycloak/` menyimpan cache
> Quarkus. File cache korup dari konfigurasi yang salah (misal port atau
> database) bisa menyebabkan Keycloak crash terus-menerus.

---

## 5. Mengirim DICOM

### 5.1. Instal Tools DICOM

```bash
# Di Arch/CachyOS
sudo pacman -S dcmtk

# Atau via Docker
docker pull dcm4che/dcm4che-tools
```

### 5.2. Kirim File via storescu

```bash
# Kirim 1 file
storescu -v -aec DCM4CHEE -aet ORTHANC localhost 11112 /path/file.dcm

# Kirim folder
storescu -v -aec DCM4CHEE -aet ORTHANC localhost 11112 /path/folder/
```

**Parameter:**
| Flag | Fungsi | Contoh |
|------|--------|--------|
| `-aec` | AE Title server tujuan | `DCM4CHEE` |
| `-aet` | AE Title client pengirim | `ORTHANC` (bebas) |
| `localhost` | Host server | bisa diganti IP `192.168.2.220` |
| `11112` | Port DICOM | port default |
| `-v` | Mode verbose (tampilkan log) | |

**Output sukses:**
```
I: Association Accepted (Max Send PDV: 16366)
I: Sending Store Request (MsgID 1, MR)
I: Received Store Response (Success)
I: Releasing Association
```

### 5.3. Kirim via Docker Tools (Alternatif)

```bash
docker run --rm \
  -v /path/to/dicom:/dicom \
  dcm4che/dcm4che-tools \
  storescu -c DCM4CHEE@192.168.2.220:11112 /dicom
```

### 5.4. Error storescu

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `Calling AE Title Not Recognized` | AE title client tidak dikenal server | Tambahkan AE title di konfigurasi server, atau pakai `STORESCU` |
| `Association Rejected` | Server tolak koneksi | Pastikan server running di port 11112 |

---

## 6. REST API JSON

dcm4chee-arc menyediakan REST API berbasis DICOMweb (DICOM PS3.18).
Semua response dalam format DICOM JSON.

### 6.1. Autentikasi

Endpoint `/aets/.../rs/...` dilindungi Keycloak OIDC. Wajib dapat token dulu:

```bash
# Dapatkan token
TOKEN=$(curl -k -X POST \
  "https://localhost:8843/realms/dcm4che/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=dcm4chee-arc-ui" \
  -d "username=root" \
  -d "password=changeit" \
  -d "grant_type=password" \
  -d "scope=openid" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])" 2>/dev/null)

# Pakai token untuk akses API
curl -k -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

### 6.2. Endpoint Utama

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| **GET** | `/aets/DCM4CHEE/rs/studies` | Query studies |
| **GET** | `/aets/DCM4CHEE/rs/studies?PatientName=KNIX` | Filter by name |
| **GET** | `/aets/DCM4CHEE/rs/studies/count` | Hitung studies |
| **GET** | `/aets/DCM4CHEE/rs/patients` | Query patients |
| **GET** | `/aets/DCM4CHEE/rs/series` | Query series |
| **GET** | `/aets/DCM4CHEE/rs/instances` | Query instances |
| **GET** | `/aets/DCM4CHEE/rs/studies/{uid}/metadata` | Metadata study |
| **GET** | `/aets/DCM4CHEE/rs/studies/{uid}/series/{sid}/instances/{iid}` | Download DICOM |
| **POST** | `/aets/DCM4CHEE/rs/studies` | Upload DICOM (STOW-RS) |

**Parameter Query:**

| Parameter | Contoh | Fungsi |
|-----------|--------|--------|
| `limit` | `?limit=10` | Batasi jumlah hasil |
| `offset` | `?offset=20` | Pagination |
| `PatientName` | `?PatientName=KNIX` | Filter nama |
| `PatientID` | `?PatientID=ozp00SjY2xG` | Filter ID pasien |
| `StudyDate` | `?StudyDate=20250115-20250120` | Range tanggal |
| `Modality` | `?Modality=MR` | Filter modality |
| `fuzzy` | `?fuzzy=true` | Pencarian samar (metaphone) |
| `includefield` | `?includefield=00100010` | Include tag spesifik |

### 6.3. Contoh Response (QIDO-RS Studies)

```json
[
  {
    "0020000D": {"vr": "UI", "Value": ["1.2.840.113619.2.176..."]},
    "00100010": {"vr": "PN", "Value": [{"Alphabetic": "KNIX"}]},
    "00100020": {"vr": "LO", "Value": ["ozp00SjY2xG"]},
    "00080020": {"vr": "DA", "Value": ["20070101"]},
    "00080061": {"vr": "CS", "Value": ["MR"]},
    "00201206": {"vr": "IS", "Value": ["1"]},
    "00201208": {"vr": "IS", "Value": ["1"]},
    "00080056": {"vr": "CS", "Value": ["ONLINE"]},
    "00081190": {"vr": "UR", "Value": ["https://...retrieve...url"]}
  }
]
```

### 6.4. Count Endpoint

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/count"
```

Response: `{"count": 2}`

### 6.5. Postman Collection

Import file `dcm4chee-postman-collection.json` ke Postman.
Set environment variables:
- `host`: IP server (default: `localhost`)
- `username`: `root`
- `password`: `changeit`

Token akan otomatis didapatkan via Pre-request Script.

---

## 7. Troubleshooting

Berikut adalah error yang paling sering muncul beserta solusinya.

### 7.1. cmp: command not found (Keycloak Exit Code 1)

```
/docker-entrypoint.sh: line 13: cmp: command not found
```

**Penyebab:** Image `dcm4che/keycloak:26.0.6` tidak punya utilitas `cmp`.
**Solusi:** Buat `Dockerfile.keycloak` dengan isi:

```dockerfile
FROM dcm4che/keycloak:26.0.6
RUN yum install -y diffutils && yum clean all && rm -rf /var/cache/yum
```

Lalu di docker-compose, ganti `image:` dengan `build:`:

```yaml
keycloak:
  build:
    context: .
    dockerfile: Dockerfile.keycloak
```

### 7.2. Quarkus augmentation completed — lalu Exit

```
Next time you run the server, just run:
  kc.sh start --import-realm --optimized
```

**Penyebab:** File cache Quarkus korup di volume `/var/local/dcm4chee-arc/keycloak/`.
**Solusi:** Hapus cache dan restart:

```bash
docker compose -p dcm4chee down
sudo rm -rf /var/local/dcm4chee-arc/keycloak/*
docker compose -p dcm4chee up -d
```

### 7.3. Unsuitable config option for services.keycloak: 'context'

```
Unsupported config option for services.keycloak: 'context'
services.keycloak.build contains an invalid type
```

**Penyebab:** Indentasi YAML salah. `context` dan `dockerfile` harus
diindentasi di bawah `build:`.

**Benar:**
```yaml
keycloak:
  build:
    context: .
    dockerfile: Dockerfile.keycloak
```

**Salah:**
```yaml
keycloak:
  build:
  context: .          # ← indentasi kurang
  dockerfile: Dockerfile.keycloak  # ← indentasi kurang
```

### 7.4. Invalid parameter: redirect_uri

```
We are sorry... Invalid parameter: redirect_uri
```

**Penyebab:** URL yang diakses di browser tidak cocok dengan
`KC_HOSTNAME` yang diset di Keycloak.

**Solusi:**
- Pastikan browser akses URL yang **sama persis** dengan `KC_HOSTNAME`
- Contoh: kalo `KC_HOSTNAME: https://192.168.2.220:8843`, browser harus
  buka `https://192.168.2.220:8843/...`, bukan `localhost`

### 7.5. self signed certificate in certificate chain

```
self signed certificate in certificate chain
```

**Penyebab:** Server menggunakan sertifikat SSL self-signed.

**Solusi:**

| Tool | Cara |
|------|------|
| **curl** | tambah flag `-k` |
| **Postman** | Settings → General → SSL certificate verification → OFF |
| **Browser** | klik Advanced → Proceed to ... (unsafe) |

### 7.6. Calling AE Title Not Recognized

```
Association Rejected: Calling AE Title Not Recognized
```

**Penyebab:** AE title client tidak terdaftar di konfigurasi server.

**Solusi:**
- Login ke UI: Configuration → Devices → dcm4chee-arc → AE Title(s)
- Tambah AE title client (misal `ORTHANC`)
- Restart container `arc`

Atau coba kirim dari **localhost** (pakai `storescu -aec DCM4CHEE -aet DCM4CHEE localhost 11112`).

### 7.7. Bind: Address Already in Use (Port Bentrok)

**Penyebab:** Port host sudah dipakai aplikasi lain.

**Cek:**
```bash
sudo lsof -i :8080
```

**Solusi:** Matikan aplikasi lain, atau ganti port mapping di docker-compose.

### 7.8. Token Expired

Token Keycloak berlaku **300 detik (5 menit)**. Kena error 401:
- Ambil token baru dengan request POST yang sama
- Atau gunakan `refresh_token`

### 7.9. Missing form parameter: grant_type

```
{"error":"invalid_request","error_description":"Missing form parameter: grant_type"}
```

**Penyebab:** Body request token di Postman pakai tipe `raw (JSON)`,
bukan `x-www-form-urlencoded`.

**Solusi:** Di Postman, tab Body → pilih **x-www-form-urlencoded**, isi
key-value, bukan JSON.

---

## 8. Daftar URL Penting

| URL | Fungsi |
|-----|--------|
| `http://localhost:8080/dcm4chee-arc/ui2` | Web UI (HTTP) |
| `https://localhost:8443/dcm4chee-arc/ui2` | Web UI (HTTPS) |
| `https://localhost:8843/admin/dcm4che/console` | Keycloak Admin Console |
| `http://localhost:9990` | Wildfly Admin Console (HTTP) |
| `https://localhost:9993` | Wildfly Admin Console (HTTPS) |
| `https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies` | REST API - Studies |
| `https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/patients` | REST API - Patients |
| `https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/series` | REST API - Series |
| `https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/instances` | REST API - Instances |
| `https://localhost:8843/realms/dcm4che/protocol/openid-connect/token` | Token endpoint |

Login default: `root` / `changeit`

---

## 9. Referensi

- Dokumentasi resmi: https://github.com/dcm4che/dcm4chee-arc-light/wiki
- Docker Compose secured services: https://github.com/dcm4che/dcm4chee-arc-light/wiki/Run-secured-archive-services-on-a-single-host
- Docker Compose minimum: https://github.com/dcm4che/dcm4chee-arc-light/wiki/Run-minimum-set-of-archive-services-on-a-single-host
- Repo docker images: https://github.com/dcm4che-dockerfiles
- Postman collection: `dcm4chee-postman-collection.json`
- API documentation detail: `API-DOKUMENTASI.md`
