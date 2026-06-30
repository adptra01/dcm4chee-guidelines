# dcm4chee-guidelines

Panduan instalasi, konfigurasi, troubleshooting, dan REST API
dcm4chee-arc-light 5.x menggunakan Docker Compose dengan Keycloak OIDC.

---

## Dokumentasi

| File | Deskripsi |
|------|-----------|
| `dcm4chee-training/PANDUAN-LENGKAP.md` | Panduan instalasi & troubleshooting lengkap |
| `dcm4chee-training/API-DOKUMENTASI.md` | Dokumentasi REST API JSON (DICOMweb) |
| `dcm4chee-training/dcm4chee-postman-collection.json` | Postman collection (import & langsung pakai) |
| `dcm4chee-training/LEVEL-1-BASIC.md` | Training level 1 (STOW-RS, QIDO-RS dasar) |
| `dcm4chee-training/LEVEL-2-INTERMEDIATE.md` | Training level 2 (MWL, WADO, OHIF) |
| `dcm4chee-training/LEVEL-3-ADVANCED.md` | Training level 3 (bulk import, storage, dll) |

---

## Arsitektur (5 Services)

| Service | Image | Fungsi | Port |
|---------|-------|--------|------|
| `ldap` | slapd-dcm4chee:2.6.10-34.3 | OpenLDAP — konfigurasi & autentikasi | 389, 636 |
| `mariadb` | mariadb:10.11.4 | Database Keycloak | 3306 |
| `keycloak` | dcm4che/keycloak:26.0.6 | Authentication server (OIDC) | 8843 |
| `db` | postgres-dcm4chee:17.4-34 | Database Archive (metadata DICOM) | 5432 |
| `arc` | dcm4chee-arc-psql:5.34.3-secure | Core PACS (STORE, Query, Retrieve) | 8080, 8443, 11112, 9990, dll |

---

## Cara Cepat

### Prasyarat

- Docker Engine 20+
- Docker Compose 2+

### 1. Clone & Masuk

```bash
git clone https://github.com/adptra01/dcm4chee-guidelines.git
cd dcm4chee-guidelines/dcm4chee-docker
```

### 2. Sesuaikan IP

Edit `docker-compose.yml`, ganti `192.168.2.220` dengan IP server lo.
Atau `localhost` kalo akses dari mesin yang sama.

### 3. Jalankan

```bash
docker compose -p dcm4chee up -d
```

### 4. Akses

| URL | Fungsi |
|-----|--------|
| `http://localhost:8080/dcm4chee-arc/ui2` | Web UI (HTTP) |
| `https://localhost:8443/dcm4chee-arc/ui2` | Web UI (HTTPS) |
| `https://localhost:8843/admin/dcm4che/console` | Keycloak Admin Console |

Login: `root` / `changeit`

### 5. Kirim DICOM

```bash
# Dari mesin yang sama
storescu -v -aec DCM4CHEE -aet ORTHANC localhost 11112 /path/file.dcm
```

---

## Error Umum

| Error | Solusi (lengkap di PANDUAN-LENGKAP.md) |
|-------|----------------------------------------|
| `cmp: command not found` | Buat `Dockerfile.keycloak` dengan `RUN yum install -y diffutils` |
| `Invalid parameter: redirect_uri` | Pastikan akses URL sama dengan `KC_HOSTNAME` |
| `Calling AE Title Not Recognized` | Tambah AE title di UI Configuration |
| Token `401` | Token expired (5 menit), ambil ulang |
| `self signed certificate` | `curl -k`, Postman SSL verification OFF |
| Keycloak crash loop | `sudo rm -rf /var/local/.../keycloak/*` lalu restart |

---

## Struktur Repo

```
dcm4chee-guidelines/
├── README.md
├── dcm4chee-docker/
│   ├── docker-compose.yml             # Main compose file
│   ├── Dockerfile.keycloak            # Build keycloak with diffutils
│   ├── docker-compose-private.yml     # Private variant
│   ├── docker-compose-public.yml      # Public variant
│   ├── data/                          # Wildfly deployments & config
│   └── sample/                        # Sample DICOM files
└── dcm4chee-training/
    ├── PANDUAN-LENGKAP.md             # Panduan lengkap (start here)
    ├── API-DOKUMENTASI.md             # REST API documentation
    ├── dcm4chee-postman-collection.json
    ├── LEVEL-1-BASIC.md
    ├── LEVEL-2-INTERMEDIATE.md
    └── LEVEL-3-ADVANCED.md
```

---

## Referensi

- [Dokumentasi resmi dcm4chee-arc-light](https://github.com/dcm4che/dcm4chee-arc-light/wiki)
- [Secured services single host](https://github.com/dcm4che/dcm4chee-arc-light/wiki/Run-secured-archive-services-on-a-single-host)
- [Minimum services single host](https://github.com/dcm4che/dcm4chee-arc-light/wiki/Run-minimum-set-of-archive-services-on-a-single-host)
- [Docker images](https://github.com/dcm4che-dockerfiles)
