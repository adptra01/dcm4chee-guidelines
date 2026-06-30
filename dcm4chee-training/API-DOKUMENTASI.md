# Dokumentasi REST API JSON dcm4chee-arc

## Base URL

```
http://<host>:8080/dcm4chee-arc/aets/DCM4CHEE/rs
https://<host>:8443/dcm4chee-arc/aets/DCM4CHEE/rs
```

### WADO-URI (legacy)

```
http://<host>:8080/dcm4chee-arc/aets/DCM4CHEE/wado
https://<host>:8443/dcm4chee-arc/aets/DCM4CHEE/wado
```

### UI2 REST (internal UI)

```
https://<host>:8443/dcm4chee-arc/ui2/rest/...
```

---

## 1. Autentikasi

Semua endpoint `/aets/.../rs/...` dilindungi OIDC Keycloak. Wajib kirim token di header:

```bash
# Dapatkan token
TOKEN=$(curl -k -X POST \
  "https://<host>:8843/realms/dcm4che/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=dcm4chee-arc-ui" \
  -d "username=root" \
  -d "password=changeit" \
  -d "grant_type=password" \
  -d "scope=openid" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

# Kirim token di header
curl -k -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

---

## 2. STOW-RS (Store Over Web) — Upload DICOM

### POST /studies

Upload file DICOM:

```bash
curl -k -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/dicom" \
  --data-binary @/path/file.dcm \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

Upload multi-file (multipart):

```bash
curl -k -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: multipart/related; type=application/dicom" \
  -F "file1=@/path/file1.dcm" \
  -F "file2=@/path/file2.dcm" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

Upload metadata JSON (tanpa pixel data):

```bash
curl -k -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "00100010": {"vr": "PN", "Value": ["SUSANTO^BUDI"]},
    "00100020": {"vr": "LO", "Value": ["P00123"]},
    "00080060": {"vr": "CS", "Value": ["CT"]}
  }' \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

**Response:**

```json
{
  "00080020": {"vr": "DA", "Value": ["20250115"]},
  "0020000D": {"vr": "UI", "Value": ["1.2.840.xxxx.xxxx"]}
}
```

---

## 3. QIDO-RS (Query) — Ambil Data

Semua response JSON dengan format DICOM JSON (tag `ggggeeee` + `vr` + `Value`).

### GET /studies

Ambil daftar studies:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
```

**Response (contoh):**

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
    "00081190": {"vr": "UR", "Value": ["https://..."]}
  }
]
```

**Parameter Query:**

| Parameter | Contoh | Deskripsi |
|-----------|--------|-----------|
| `PatientID` | `PatientID=P00123` | Filter by Patient ID |
| `PatientName` | `PatientName=KNIX` | Filter by Patient Name |
| `StudyDate` | `StudyDate=20250115-20250120` | Range tanggal (YYYYMMDD-YYYYMMDD) |
| `Modality` | `Modality=CT` | Filter modality |
| `limit` | `limit=10` | Maksimal hasil per halaman |
| `offset` | `offset=20` | Pagination offset |
| `fuzzy` | `fuzzy=true` | Fuzzy name matching (metaphone) |
| `includefield` | `includefield=00100010` | Include tag spesifik di response |

### GET /studies/{StudyUID}/series

Ambil series dalam study:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.xxx/series"
```

### GET /series/.../instances

Ambil instances dalam series:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/series/1.2.840.xxx/instances"
```

### GET /studies — via UI path

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.xxx/series/1.2.840.xxx/instances"
```

### GET /patients

Ambil daftar pasien:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/patients?PatientName=KNIX"
```

### GET /count endpoints

```bash
# Count studies
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/count"

# Count patients
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/patients/count"

# Count series
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/series/count"

# Count instances
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/instances/count"

# Count MWL items
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems/count"
```

**Response count:**

```json
{"count": 2}
```

---

## 4. WADO-RS (Retrieve) — Download/Metadata

### GET /studies/{StudyUID}/metadata

Ambil metadata study lengkap:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/dicom+json" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.xxx/metadata"
```

### GET /studies/{StudyUID}/series/{SeriesUID}/instances/{InstanceUID}

Download DICOM file asli:

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  -o /tmp/download.dcm \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/studies/1.2.840.xxx/series/1.2.840.xxx/instances/1.2.840.xxx"
```

### WADO-URI (thumbnail JPEG)

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  -o /tmp/preview.jpg \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/wado?requestType=WADO&studyUID=1.2.840.xxx&contentType=image/jpeg"
```

---

## 5. MWL-RS (Modality Worklist)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/mwlitems` | Query MWL items |
| POST | `/mwlitems` | Create MWL item |
| PUT | `/mwlitems/{studyIUID}/{spsID}` | Update MWL status |

```bash
curl -k -H "Authorization: Bearer $TOKEN" \
  "https://localhost:8443/dcm4chee-arc/aets/DCM4CHEE/rs/mwlitems?PatientID=P00123"
```

---

## 6. UPS-RS (Unified Procedure Step)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/workitems` | Query work items |
| POST | `/workitems` | Create work item |
| DELETE | `/workitems/{workitem}` | Delete work item |

---

## 7. Daftar Lengkap Endpoint

### QIDO-RS (Query)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/studies` | Query studies |
| GET | `/studies/{studyUID}` | Single study metadata |
| GET | `/studies/{studyUID}/series` | Series dalam study |
| GET | `/series` | Query all series |
| GET | `/series/{seriesUID}/instances` | Instances dalam series |
| GET | `/instances` | Query all instances |
| GET | `/patients` | Query patients |
| GET | `/modalities` | List modalities |
| GET | `/mwlitems` | Query MWL items |
| GET | `/workitems` | Query UPS items |
| GET | `*/count` | Count endpoint untuk semua resource |

### WADO-RS (Retrieve)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/studies/{studyUID}/metadata` | Metadata study |
| GET | `/studies/{studyUID}/series/{seriesUID}/instances/{objectUID}` | Download instance |
| GET | `/studies/{studyUID}/series/{seriesUID}/instances/{objectUID}/frames/{frames}` | Download frames |
| GET | `/studies/{studyUID}/series/{seriesUID}/instances/{objectUID}/rendered` | Rendered image |
| GET | `/studies/{studyUID}/series/{seriesUID}/instances/{objectUID}/thumbnail` | Thumbnail |

### STOW-RS (Store)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/studies` | Store DICOM |
| POST | `/studies/{StudyInstanceUID}` | Store ke study spesifik |

### MWL-RS

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET/POST | `/mwlitems` | Query / Create MWL |
| PUT | `/mwlitems/{uid}` | Update MWL |

### UPS-RS

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET/POST | `/workitems` | Query / Create UPS |
| DELETE | `/workitems/{uid}` | Delete UPS |

### Monitoring / Admin

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/monitor` | Archive monitor |
| GET | `/serverTime` | Server time |
| GET | `/metrics` | Archive metrics |
| GET | `/host` | Server host info |
| GET | `/storage` | Storage status |

### Export

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/studies/export/{ExporterID}` | Export studies |
| POST | `/series/export/{ExporterID}` | Export series |
| POST | `/instances/export/{ExporterID}` | Export instances |

### Bulk Import

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/instances/storage/{StorageID}` | Bulk import dari storage path |

---

## 8. Format Response JSON

Response menggunakan **DICOM JSON Model** (PS3.18):

```json
{
  "ggggeeee": {
    "vr": "VR_CODE",
    "Value": [...]
  }
}
```

| Tag | VR | Field |
|-----|-----|-------|
| `00100010` | PN | Patient Name |
| `00100020` | LO | Patient ID |
| `00100030` | DA | Patient Birth Date |
| `00100040` | CS | Patient Sex |
| `0020000D` | UI | Study Instance UID |
| `0020000E` | UI | Series Instance UID |
| `00080018` | UI | SOP Instance UID |
| `00080020` | DA | Study Date |
| `00080030` | TM | Study Time |
| `00080060` | CS | Modality |
| `00080061` | CS | Modalities In Study |
| `0008103E` | LO | Series Description |
| `00201206` | IS | Number of Study Related Series |
| `00201208` | IS | Number of Study Related Instances |
| `00080056` | CS | Instance Availability |
| `00081190` | UR | Retrieve URL |

---

## 9. Kode Error HTTP

| Kode | Arti |
|------|------|
| 200 | OK |
| 202 | Accepted (async task) |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized (token invalid/expired) |
| 403 | Forbidden |
| 404 | Not Found |
| 409 | Conflict |
| 422 | Unprocessable Entity |
| 500 | Internal Server Error |

---

## 10. Tips Penggunaan

1. **SSL Self-Signed**: Semua contoh di atas pakai `-k` (curl) untuk bypass SSL. Di Postman, matikan **SSL certificate verification** di Settings.

2. **Token Expired**: Token berlaku 300 detik (5 menit). Pakai `refresh_token` untuk dapat token baru.

3. **AE Title**: Default `DCM4CHEE`. Ganti sesuai konfigurasi server.

4. **Port**: HTTP di port 8080, HTTPS di port 8443. Keycloak di port 8843.

5. **Pagination**: Selalu set `limit` dan `offset` untuk performance.

6. **Filter**: Parameter query bisa dikombinasi, e.g. `?PatientName=KNIX&Modality=MR&limit=10`.
