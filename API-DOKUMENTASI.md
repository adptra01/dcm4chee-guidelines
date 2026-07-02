# Dokumentasi REST API dcm4chee-arc 5.x

Dokumentasi mandiri untuk REST API DICOMweb server dcm4chee-arc-light 5.x.
Berdasarkan hasil eksplorasi langsung ke server dan analisis endpoint.

---

## Daftar Isi

1. [Informasi Server](#1-informasi-server)
2. [Autentikasi OIDC (Keycloak)](#2-autentikasi-oidc-keycloak)
3. [QIDO-RS (Query)](#3-qido-rs-query)
4. [STOW-RS (Store)](#4-stow-rs-store)
5. [WADO-RS (Retrieve)](#5-wado-rs-retrieve)
6. [WADO-URI](#6-wado-uri)
7. [MWL-RS (Modality Worklist)](#7-mwl-rs-modality-worklist)
8. [UPS-RS (Unified Procedure Steps)](#8-ups-rs-unified-procedure-steps)
9. [Monitoring & Management](#9-monitoring--management)
10. [Export & Import](#10-export--import)
11. [Tag DICOM Penting](#11-tag-dicom-penting)
12. [Kode Error](#12-kode-error)
13. [Contoh Lengkap (cURL)](#13-contoh-lengkap-curl)

---

## 1. Informasi Server

### Base URL

```
https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs
```

| Komponen | Nilai |
|----------|-------|
| Host | IP atau hostname server (contoh: `{{host}}`) |
| Port | `8443` (HTTPS) atau `8080` (HTTP) |
| AET | `DCM4CHEE` (Application Entity Title) |

### Port yang Digunakan

| Port | Fungsi |
|------|--------|
| `8080` | HTTP Archive UI & REST API |
| `8443` | HTTPS Archive UI & REST API (secure) |
| `8843` | Keycloak Admin Console (via host) |
| `11112` | DICOM DIMSE (store, echo, dll) |
| `9990` | Wildfly Management Console |
| `5432` | PostgreSQL (internal) |
| `3306` | MariaDB Keycloak (internal) |
| `389` | OpenLDAP (internal) |

### URL Penting

| URL | Fungsi |
|-----|--------|
| `https://{host}:8443/dcm4chee-arc/ui2` | Web UI Archive |
| `https://{host}:8843/admin/dcm4che/console` | Keycloak Admin Console |
| `https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs` | REST API Base |

---

## 2. Autentikasi OIDC (Keycloak)

Semua endpoint REST API (kecuali monitoring tertentu) WAJIB menyertakan token
OIDC dari Keycloak.

### 2.1 Ambil Token

```
POST https://{host}:8843/realms/dcm4che/protocol/openid-connect/token
Content-Type: application/x-www-form-urlencoded

client_id=dcm4chee-arc-rs&client_secret=changeit&username=admin&password=changeit&grant_type=password
```

**Response:**

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "expires_in": 300,
  "refresh_expires_in": 1800,
  "refresh_token": "eyJhbGciOiJIUzUxMiIs...",
  "token_type": "Bearer",
  "not-before-policy": 0,
  "session_state": "...",
  "scope": "openid email profile"
}
```

### 2.2 Gunakan Token

```
GET https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
Accept: application/dicom+json
```

### 2.3 Refresh Token

```
POST https://{host}:8843/realms/dcm4che/protocol/openid-connect/token
Content-Type: application/x-www-form-urlencoded

client_id=dcm4chee-arc&refresh_token=...&grant_type=refresh_token
```

### Catatan Penting

- **Client ID**: `dcm4chee-arc-rs` (bukan `dcm4chee-arc`) — ini adalah confidential client
- **Client Secret**: `changeit` — wajib disertakan untuk client confidential
- Token berlaku **300 detik (5 menit)** — harus refresh secara berkala
- Gunakan `refresh_token` untuk mendapat token baru tanpa login ulang
- Sertakan header `Authorization: Bearer {token}` di setiap request
- Untuk development, matikan SSL verification: `curl -k`
- Di Postman: Setting > SSL Verification = OFF

---

## 3. QIDO-RS (Query)

Query IDentifier Object — mencari studies, series, dan instances.

### 3.1 Cari Studies

```
GET {base}/studies
Accept: application/dicom+json
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Contoh | Deskripsi |
|-----------|--------|-----------|
| `PatientName` | `Smit*` | Cari wildcard |
| `PatientID` | `12345` | Exact match |
| `StudyDate` | `20250115` | Tanggal spesifik |
| `StudyDate` | `20250101-20250131` | Rentang tanggal |
| `ModalitiesInStudy` | `CT` | Filter modalitas |
| `StudyDescription` | `*CHEST*` | Deskripsi studi |
| `AccessionNumber` | `ACC001` | Nomor akses RIS |
| `StudyInstanceUID` | `1.2.840.113619...` | UID spesifik |
| `limit` | `50` | Maksimal hasil |
| `offset` | `0` | Halaman |
| `includefield` | `all` | Semua field |
| `includefield` | `00100010,00080060` | Field tertentu |
| `fuzzymatching` | `true` | Fuzzy search |

**Response (application/dicom+json):**

```json
[
  {
    "00080005": { "vr": "CS", "Value": ["ISO_IR 100"] },
    "00080020": { "vr": "DA", "Value": ["20250115"] },
    "00080030": { "vr": "TM", "Value": ["103000"] },
    "00080060": { "vr": "CS", "Value": ["CT"] },
    "00081030": { "vr": "LO", "Value": ["CT CHEST W CONTRAST"] },
    "00100010": {
      "vr": "PN",
      "Value": [{ "Alphabetic": "Doe^Jane" }]
    },
    "00100020": { "vr": "LO", "Value": ["PAT001"] },
    "00100030": { "vr": "DA", "Value": ["19800515"] },
    "00100040": { "vr": "CS", "Value": ["F"] },
    "0020000D": {
      "vr": "UI",
      "Value": ["1.2.840.113619.2.415.3.283116.20250115.1"]
    }
  }
]
```

### 3.2 Cari Series dalam Study

```
GET {base}/studies/{StudyInstanceUID}/series
Accept: application/dicom+json
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Contoh |
|-----------|--------|
| `Modality` | `CT` |
| `SeriesDescription` | `*CHEST*` |
| `SeriesInstanceUID` | `1.2.840...` |

### 3.3 Cari Instances dalam Series

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 3.4 Cari Instances dalam Study (semua series)

```
GET {base}/studies/{StudyInstanceUID}/instances
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 3.5 Query Semua Series (tanpa filter study)

```
GET {base}/series
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 3.6 Query Semua Instances (tanpa filter study/series)

```
GET {base}/instances
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 3.7 Contoh Response Series

```json
[
  {
    "00080060": { "vr": "CS", "Value": ["CT"] },
    "0008103E": { "vr": "LO", "Value": ["AXIAL 2.5"] },
    "0020000D": { "vr": "UI", "Value": ["1.2.840.113619..."] },
    "0020000E": { "vr": "UI", "Value": ["1.2.840.113619..."] },
    "00200011": { "vr": "IS", "Value": ["2"] },
    "00201209": { "vr": "IS", "Value": ["150"] }
  }
]
```

### 3.8 Contoh Response Instance

```json
[
  {
    "00080016": { "vr": "UI", "Value": ["1.2.840.10008.5.1.4.1.1.2"] },
    "00080018": { "vr": "UI", "Value": ["1.2.840.113619..."] },
    "00080060": { "vr": "CS", "Value": ["CT"] },
    "0020000D": { "vr": "UI", "Value": ["1.2.840.113619..."] },
    "0020000E": { "vr": "UI", "Value": ["1.2.840.113619..."] },
    "00200013": { "vr": "IS", "Value": ["15"] }
  }
]
```

---

## 4. STOW-RS (Store)

Store Over the Web — upload file DICOM ke server.

### 4.1 Upload Instance Baru

```
POST {base}/studies
Content-Type: multipart/related; type="application/dicom"; boundary=boundary123
Accept: application/dicom+json
Authorization: Bearer {token}

--boundary123
Content-Type: application/dicom

{binary DICOM data}
--boundary123
Content-Type: application/dicom

{binary DICOM data}
--boundary123--
```

### 4.2 Upload ke Study yang Sudah Ada

```
POST {base}/studies/{StudyInstanceUID}
Content-Type: multipart/related; type="application/dicom"; boundary=boundary123
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 4.3 Response STOW-RS

```json
{
  "00081190": {
    "vr": "UR",
    "Value": ["https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840..."]
  },
  "00081198": {
    "vr": "SQ",
    "Value": []
  },
  "00081199": {
    "vr": "SQ",
    "Value": [
      {
        "00081150": {
          "vr": "UI",
          "Value": ["1.2.840.10008.5.1.4.1.1.2"]
        },
        "00081155": {
          "vr": "UI",
          "Value": ["1.2.840.113619.2.415.3.283116.20250115.1.2"]
        },
        "00081190": {
          "vr": "UR",
          "Value": ["https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/..."]
        }
      }
    ]
  }
}
```

| Tag | Arti |
|-----|------|
| `00081190` | URL retrieve study |
| `00081198` | Failed SOP Sequence (kosong = berhasil semua) |
| `00081199` | Referenced SOP Sequence (daftar instance tersimpan) |
| `00081150` | Referenced SOP Class UID |
| `00081155` | Referenced SOP Instance UID |

### 4.4 Upload via DIMAE (storescu)

Alternatif menggunakan protokol DICOM native:

```bash
storescu -v -aec DCM4CHEE -aet MYAE localhost 11112 /path/file.dcm
```

Parameter:
- `-aec DCM4CHEE` : Calling AE Title (Application Entity Title server)
- `-aet MYAE` : Sending AE Title (identitas pengirim)
- `localhost 11112` : Host dan port DICOM server

---

## 5. WADO-RS (Retrieve)

Web Access to DICOM Objects — mengambil data DICOM.

### 5.1 Retrieve Study Metadata

```
GET {base}/studies/{StudyInstanceUID}/metadata
Accept: application/dicom+json
Authorization: Bearer {token}
```

Response: daftar metadata JSON seluruh instance dalam study (tanpa pixel data).

### 5.2 Retrieve Series Metadata

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/metadata
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 5.3 Retrieve Instance Metadata

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances/{SOPInstanceUID}/metadata
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 5.4 Retrieve Study (semua instance)

```
GET {base}/studies/{StudyInstanceUID}
Accept: multipart/related; type="application/dicom"
Authorization: Bearer {token}
```

Response: multipart DICOM — seluruh file DICOM dalam study.

### 5.5 Retrieve Series (semua instance dalam series)

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}
Accept: multipart/related; type="application/dicom"
Authorization: Bearer {token}
```

### 5.6 Retrieve Instance (satu file DICOM)

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances/{SOPInstanceUID}
Accept: multipart/related; type="application/dicom"
Authorization: Bearer {token}
```

Response: multipart DICOM — berisi satu atau lebih file DICOM.

> **Catatan:** Gunakan `Accept: multipart/related; type="application/dicom"`.
> `Accept: application/dicom` saja akan mengembalikan HTTP 406 (Not Acceptable).

### 5.7 Retrieve Rendered Image

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances/{SOPInstanceUID}/rendered
Accept: image/png
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Contoh | Deskripsi |
|-----------|--------|-----------|
| `window` | `40,400` | Window center,width (CT) |
| `viewport` | `256,256` | Maksimal width,height |
| `quality` | `80` | Kualitas JPEG (1-100) |
| `frameNumber` | `1` | Nomor frame (multi-frame) |

### 5.8 Retrieve Specific Frames

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances/{SOPInstanceUID}/frames/1,2,3
Accept: multipart/related; type="application/dicom"
Authorization: Bearer {token}
```

### 5.9 Retrieve Thumbnail

```
GET {base}/studies/{StudyInstanceUID}/series/{SeriesInstanceUID}/instances/{SOPInstanceUID}/thumbnail
Accept: image/png
Authorization: Bearer {token}
```

---

## 6. WADO-URI

Web Access to DICOM Objects via URI — parameter dalam query string.

### 6.1 Retrieve Instance

```
GET {base}/wado?requestType=WADO&studyUID={StudyInstanceUID}&seriesUID={SeriesInstanceUID}&objectUID={SOPInstanceUID}&contentType=application/dicom
Authorization: Bearer {token}
```

### 6.2 Retrieve Rendered Image

```
GET {base}/wado?requestType=WADO&studyUID={uid}&seriesUID={uid}&objectUID={uid}&contentType=image/png&windowCenter=40&windowWidth=400
Authorization: Bearer {token}
```

Base URL WADO-URI:
```
https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/wado
```

---

## 7. MWL-RS (Modality Worklist)

Modality Worklist — jadwalkan pemeriksaan untuk modalitas.

### 7.1 Query Worklist

```
GET {base}/workitems
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 7.2 Filter Worklist by Modality

```
GET {base}/workitems?Modality=CT
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 7.3 Create Workitem

```
POST {base}/workitems
Content-Type: application/dicom+json
Authorization: Bearer {token}

{
  "00400100": {
    "vr": "SQ",
    "Value": [...]
  }
}
```

### 7.4 Delete Workitem

```
DELETE {base}/workitems/{WorkitemUID}
Authorization: Bearer {token}
```

Base URL MWL-RS:
```
https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/mwl
```

---

## 8. UPS-RS (Unified Procedure Steps)

Unified Procedure Steps — manajemen prosedur.

### 8.1 Get UPS

```
GET {base}/UPS/{UPSInstanceUID}
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 8.2 Search UPS

```
GET {base}/UPS?TransactionUID={uid}
Accept: application/dicom+json
Authorization: Bearer {token}
```

### 8.3 Update UPS State

```
POST {base}/UPS/{UPSInstanceUID}/state/{EventType}
Content-Type: application/dicom+json
Authorization: Bearer {token}
```

Base URL UPS-RS:
```
https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/ups
```

---

## 9. Monitoring & Management

### 9.1 Health Check

```
GET {base}/monitoring/health
Authorization: Bearer {token}
```

Response:
```json
{
  "status": "UP",
  "checks": [
    { "name": "dicom", "status": "UP" },
    { "name": "ldap", "status": "UP" },
    { "name": "db", "status": "UP" }
  ]
}
```

### 9.2 Informasi Device

```
GET {base}/monitoring/device
Authorization: Bearer {token}
```

Response: informasi device name, AE title, versi.

### 9.3 Informasi AET (Application Entity)

```
GET {base}/monitoring/aets
Authorization: Bearer {token}
```

Response: daftar AE title yang terdaftar dan statusnya.

### 9.4 Statistik

```
GET {base}/monitoring/statistics
Authorization: Bearer {token}
```

### 9.5 C-Echo (DICOM Connectivity)

```
GET {base}/monitoring/echoscp
Authorization: Bearer {token}
```

### 9.6 Daftar Tasks (Background Jobs)

```
GET {base}/monitoring/tasks
Authorization: Bearer {token}
```

### 9.7 Status Task Spesifik

```
GET {base}/monitoring/tasks/{TaskPK}
Authorization: Bearer {token}
```

---

## 10. Export & Import

### 10.1 Export Study (dicom: protocol)

```
POST {base}/studies/{StudyInstanceUID}/export/dicom
Content-Type: application/json
Authorization: Bearer {token}

{
  "DestinationAET": "TARGET_AE",
  "DestinationHost": "192.168.1.100",
  "DestinationPort": 11112,
  "Priority": 0
}
```

### 10.2 Export Study (STOW-RS)

```
POST {base}/studies/{StudyInstanceUID}/export/stow
Content-Type: application/json
Authorization: Bearer {token}

{
  "StowURL": "https://target-server:8443/dcm4chee-arc/aets/TARGET/rs/studies",
  "StowUser": "user",
  "StowPassword": "pass"
}
```

### 10.3 Export Study (file system)

```
POST {base}/studies/{StudyInstanceUID}/export/file
Content-Type: application/json
Authorization: Bearer {token}

{
  "ExportDir": "/storage/export"
}
```

### 10.4 Cek Status Export

```
GET {base}/monitoring/tasks
Authorization: Bearer {token}
```

Cari task dengan type `Export` untuk melihat progres.

### 10.5 Import DICOM via REST (STOW-RS)

Upload langsung ke server seperti dijelaskan di [STOW-RS](#4-stow-rs-store).

### 10.6 Import via DICOM Schedule

```
POST {base}/patients
Content-Type: application/json
Authorization: Bearer {token}

{
  "PatientName": "Doe^Jane",
  "PatientID": "PAT001",
  "PatientBirthDate": "19800515",
  "PatientSex": "F"
}
```

---

## 11. Tag DICOM Penting

### Patient Tags

| Tag | Keyword | VR | Deskripsi |
|-----|---------|----|-----------|
| `00100010` | PatientName | PN | Nama pasien |
| `00100020` | PatientID | LO | ID pasien |
| `00100030` | PatientBirthDate | DA | Tanggal lahir |
| `00100040` | PatientSex | CS | Jenis kelamin |
| `00100021` | IssuerOfPatientID | LO | Penerbit ID |

### Study Tags

| Tag | Keyword | VR | Deskripsi |
|-----|---------|----|-----------|
| `0020000D` | StudyInstanceUID | UI | UID studi |
| `00080020` | StudyDate | DA | Tanggal studi |
| `00080030` | StudyTime | TM | Waktu studi |
| `00080050` | AccessionNumber | SH | Nomor akses |
| `00080060` | Modality | CS | Modalitas |
| `00081030` | StudyDescription | LO | Deskripsi studi |
| `00080090` | ReferringPhysicianName | PN | Dokter pengirim |
| `00080096` | ReferringPhysicianIdentification | SQ | ID dokter pengirim |
| `00101010` | PatientAge | AS | Usia pasien |
| `00101030` | PatientWeight | DS | Berat badan |
| `001021B0` | AdditionalPatientHistory | LT | Riwayat tambahan |
| `00321060` | RequestedProcedureDescription | LO | Deskripsi prosedur |

### Series Tags

| Tag | Keyword | VR | Deskripsi |
|-----|---------|----|-----------|
| `0020000E` | SeriesInstanceUID | UI | UID series |
| `00200011` | SeriesNumber | IS | Nomor series |
| `0008103E` | SeriesDescription | LO | Deskripsi series |
| `00080060` | Modality | CS | Modalitas |
| `00201209` | NumberOfSeriesRelatedInstances | IS | Jumlah instance |

### Instance Tags

| Tag | Keyword | VR | Deskripsi |
|-----|---------|----|-----------|
| `00080016` | SOPClassUID | UI | Kelas SOP |
| `00080018` | SOPInstanceUID | UI | UID instance |
| `00200013` | InstanceNumber | IS | Nomor instance |

### Tags Lain

| Tag | Keyword | VR | Deskripsi |
|-----|---------|----|-----------|
| `00080005` | SpecificCharacterSet | CS | Set karakter |
| `00080008` | ImageType | CS | Tipe citra |
| `00280002` | SamplesPerPixel | US | Sample per pixel |
| `00280004` | PhotometricInterpretation | CS | Interpretasi fotometrik |
| `00280010` | Rows | US | Tinggi citra |
| `00280011` | Columns | US | Lebar citra |
| `00280100` | BitsAllocated | US | Bit per pixel |
| `00280101` | BitsStored | US | Bit tersimpan |
| `00280102` | HighBit | US | High bit |
| `00281050` | WindowCenter | DS | Window center |
| `00281051` | WindowWidth | DS | Window width |
| `00281052` | RescaleIntercept | DS | Rescale intercept |
| `00281053` | RescaleSlope | DS | Rescale slope |
| `00080016` | SOPClassUID | UI | UID kelas SOP |

### VR (Value Representation) Types

| VR | Arti | Contoh |
|----|------|--------|
| PN | Person Name | `{ "Alphabetic": "Doe^Jane" }` |
| DA | Date | `"20250115"` |
| TM | Time | `"103000.123"` |
| DT | Date Time | `"20250115103000"` |
| CS | Code String | `"CT"` |
| LO | Long String | `"CT CHEST"` |
| SH | Short String | `"ACC001"` |
| UI | UID | `"1.2.840.10008..."` |
| IS | Integer String | `"150"` |
| DS | Decimal String | `"40.5"` |
| SQ | Sequence | Array of objects |
| US | Unsigned Short | `512` |
| FD | Floating Double | `0.5` |

---

## 12. Kode Error

### HTTP Status Codes

| Status | Arti | Penyebab Umum |
|--------|------|---------------|
| `200 OK` | Sukses | Request berhasil |
| `202 Accepted` | Diterima | Export/import task dibuat |
| `204 No Content` | Sukses tanpa body | DELETE berhasil |
| `400 Bad Request` | Request salah | Parameter invalid, JSON salah |
| `401 Unauthorized` | Token tidak valid | Token expired / salah |
| `403 Forbidden` | Tidak punya akses | User tidak punya role / study tidak bisa dihapus langsung |
| `404 Not Found` | Resource tidak ditemukan | UID salah / tidak ada / exporter tidak terdaftar |
| `406 Not Acceptable` | Accept header salah | Gunakan `multipart/related; type="application/dicom"` |
| `409 Conflict` | Konflik | Instance sudah ada |
| `415 Unsupported Media Type` | Media type salah | Content-Type multipart salah |
| `422 Unprocessable Entity` | Data tidak valid | Tag DICOM tidak valid |
| `500 Internal Server Error` | Server error | Bug / konfigurasi salah |
| `503 Service Unavailable` | Service tidak tersedia | DB down / LDAP error |

### Error Response Format

```json
{
  "00081198": {
    "vr": "SQ",
    "Value": [
      {
        "00081150": {
          "vr": "UI",
          "Value": ["1.2.840.10008.5.1.4.1.1.2"]
        },
        "00081155": {
          "vr": "UI",
          "Value": ["1.2.840.113619..."]
        },
        "00081197": {
          "vr": "US",
          "Value": [AXX]
        },
        "00081199": {
          "vr": "SQ",
          "Value": []
        }
      }
    ]
  }
}
```

### Kode Error (00081197)

| Kode | Arti |
|------|------|
| `A100` | Processing failure |
| `A101` | No such object |
| `A102` | Refused |
| `A103` | Error - dataset or query missing required attribute |
| `A104` | Duplicate |
| `A105` | Warning - resources may be inconsistent |
| `A106` | Warning - data set does not match |
| `A107` | Spilt study |
| `A201` | Coercion of range |
| `A202` | Coercion of data element |
| `A301` | Coercion of object UIDs |
| `A302` | Aborted |

### Troubleshooting

| Error | Solusi |
|-------|--------|
| `invalid_client` | Gunakan `client_id=dcm4chee-arc-rs` dengan `client_secret=changeit` |
| `401` token expired | Ambil ulang token via `/token` endpoint |
| `400` multipart salah | Pastikan boundary string benar dan unik |
| `403` DELETE ditolak | Study harus di-reject dulu sebelum dihapus |
| `404` export | Exporter default mungkin tidak aktif; periksa konfigurasi |
| `406` download instance | Set `Accept: multipart/related; type="application/dicom"` |
| `415` | Set `Content-Type: multipart/related; type="application/dicom"; boundary=...` |
| Study tidak muncul | Tunggu index selesai (beberapa detik) |
| DICOM C-ECHO gagal | Pastikan AE title terdaftar di UI Configuration |

---

## 13. Contoh Lengkap (cURL)

### 13.1 Ambil Token

```bash
TOKEN=$(curl -sk -X POST "https://{{host}}:8843/realms/dcm4che/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=dcm4chee-arc-rs&client_secret=changeit&username=admin&password=changeit&grant_type=password" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
```

### 13.2 Cari Studies

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 13.3 Cari Studies dengan Filter

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies?PatientName=Doe*&ModalitiesInStudy=CT&limit=10" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 13.4 Cari Series dalam Study

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{StudyInstanceUID}/series" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 13.5 Retrieve Metadata Study

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{StudyInstanceUID}/metadata" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 13.6 Download Instance DICOM

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{uid}/series/{uid}/instances/{uid}" \
  -H 'Accept: multipart/related; type="application/dicom"' \
  -H "Authorization: Bearer $TOKEN" \
  -o image.dcm
```

### 13.7 Retrieve Rendered PNG

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{uid}/series/{uid}/instances/{uid}/rendered" \
  -H "Accept: image/png" \
  -H "Authorization: Bearer $TOKEN" \
  -o output.png
```

### 13.8 Upload DICOM via STOW-RS

```bash
curl -sk -X POST "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies" \
  -H "Content-Type: multipart/related; type=\"application/dicom\"; boundary=DCM4BOUNDARY" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary $'--DCM4BOUNDARY\r\nContent-Type: application/dicom\r\n\r\n'$(cat file.dcm)$'\r\n--DCM4BOUNDARY--\r\n'
```

Atau menggunakan file:

```bash
BOUNDARY="BOUNDARY$(date +%s)"
{
  for f in /path/to/dicom/*.dcm; do
    echo "--$BOUNDARY"
    echo "Content-Type: application/dicom"
    echo ""
    cat "$f"
    echo ""
  done
  echo "--$BOUNDARY--"
} > /tmp/multipart.txt

curl -sk -X POST "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies" \
  -H "Content-Type: multipart/related; type=\"application/dicom\"; boundary=$BOUNDARY" \
  -H "Accept: application/dicom+json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary @/tmp/multipart.txt
```

### 13.9 Health Check

```bash
curl -sk "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/monitoring/health" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

### 13.10 Export Study via DICOM

```bash
curl -sk -X POST "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{StudyInstanceUID}/export/dicom" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "DestinationAET": "TARGET_AE",
    "DestinationHost": "192.168.1.100",
    "DestinationPort": 11112
  }'
```

### 13.11 Delete Study

```bash
curl -sk -X DELETE "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{StudyInstanceUID}" \
  -H "Authorization: Bearer $TOKEN"
```

### 13.12 Re-index Study

```bash
curl -sk -X POST "https://{{host}}:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/{StudyInstanceUID}/reindex" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Referensi

- Dokumentasi ini berdasarkan eksplorasi langsung ke server dcm4chee-arc-light 5.x
- Format response: DICOM JSON Model (PS3.18)
- Base URL: `https://{host}:8443/dcm4chee-arc/aets/DCM4CHEE/rs`

---

*Dokumentasi ini dibuat berdasarkan hasil eksplorasi langsung ke server dan tidak menyalin dari repositori pihak ketiga.*
