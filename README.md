# PACS Stack — Orthanc + OHIF + PostgreSQL

Integrasi PZDR (DR FORERMED) ke PACS self-hosted. Semua berjalan di Docker Compose, dioperasikan sendiri tanpa vendor.

## Arsitektur

```
PZDR (Windows, DICOM SCU)
   │  C-STORE → port 4242 (AE: ORTHANC)
   ▼
Orthanc (Docker) ──► PostgreSQL (index/metadata)
   │  ├─ REST API + DICOMweb → :8042
   │  └─ Web Viewer bawaan
   ▼
OHIF Viewer (Docker) → http://<host>:3000
```

## Quick Start

```bash
cp .env.example .env        # sesuaikan bila perlu
docker compose up -d
```

| Endpoint | URL |
|---|---|
| Orthanc REST / DICOMweb | `http://<host>:8042` (DICOMweb di `/dicom-web`) |
| Orthanc UI (Explorer) | `http://<host>:8042/` |
| OHIF Viewer | `http://<host>:3000` |
| DICOM (untuk PZDR) | `<host>:4242`, AE Title: `ORTHANC` |

## Uji Cepat DICOM (dari mesin dengan dcmtk)

```bash
echoscu  -aec ORTHANC -aet TEST <host> 4242        # C-ECHO
storescu -aec ORTHANC -aet PZDR_DR1 <host> 4242 file.dcm   # C-STORE
```

## Struktur Folder

```
├── docker-compose.yml      # stack: db + orthanc + ohif
├── .env.example            # port & kredensial (salin ke .env)
├── orthanc/orthanc.json    # konfigurasi Orthanc (AE, port, plugin)
├── ohif/app-config.js      # konfigurasi viewer (ubah localhost → IP server)
├── pzdr/                   # installer PZDR + manual
├── data/                   # data runtime (DICOM storage) — backup ini
├── docs/                   # panduan integrasi, troubleshooting, ATP
└── archive/                # proyek lama (dcm4chee, laravel-pacs) — referensi
```

## Operasi

```bash
docker compose ps                 # status
docker compose logs -f orthanc    # log Orthanc (diagnosa koneksi PZDR)
docker compose restart orthanc    # restart setelah ubah orthanc.json
docker compose down               # stop, data tersimpan
docker compose down -v            # HAPUS SEMUA DATA — jangan untuk produksi
```

## Konfigurasi di sisi PZDR

Buka **Configuration Tools** (password: `1`) → **4.6.4 PACS Configuration**:

| Field PZDR | Nilai |
|---|---|
| Host AETitle | `PZDR_DR1` (bebas, unik) |
| AETitle | `ORTHANC` (harus sama persis) |
| Hostname | IP mesin Docker (bukan `localhost`) |
| Port | `4242` |
| Auto send | OFF dulu |

Langkah detail: `docs/PZDR-INTEGRATION.md`

## Catatan Penting

- **Akses lintas jaringan** (VLAN, IP berubah, lokasi terpisah) → `docs/ACCESS-JARINGAN.md`
- **Ganti `localhost` di `ohif/app-config.js`** dengan IP mesin Docker jika viewer diakses dari mesin lain (mis. workstation PZDR).
- Plugin Orthanc dimuat dari **direktori** (`/usr/local/share/orthanc/plugins`) — memuat semua plugin bawaan image (PostgreSQL, DICOMweb, GDCM, OHIF, UI Explorer). Jangan ganti menjadi daftar file individu (itu memutus UI bawaan).
- Authentication REST Orthanc sengaja **nonaktif** untuk lab. Untuk produksi aktifkan (lihat `docs/TROUBLESHOOTING.md` → Keamanan).
- Backup = folder `data/orthanc` (storage DICOM) + `pg_dump` volume `pacs-db-data` (lihat `docs/TROUBLESHOOTING.md`).
