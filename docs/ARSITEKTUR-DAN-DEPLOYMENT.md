# Arsitektur dan Deployment PACS DCM4CHEE

## 1. Soal Istilah "Portal"

Sistem yang dibangun (Laravel/Filament + QIDO-RS/STOW-RS/WADO-RS/MWL-RS) menggabungkan dua peran yang di industri biasa dipisah:

- **Order entry / worklist front-end** — fungsi mirip modul depan RIS (Radiology Information System)
- **Web viewer gateway** — fungsi zero-footprint viewer yang mengambil dan menampilkan studi dari PACS

Istilah tepat untuk dokumentasi: **"Portal Klinis Radiologi"** atau **"RIS-lite / Clinical Imaging Portal"**.
Ini bukan RIS penuh (belum ada billing, scheduling kompleks, dsb.) tapi juga bukan sekadar viewer — hybrid yang cukup umum di sistem modern berbasis REST.

---

## 2. Arsitektur End-to-End

```
Portal (Laravel/Filament)
  │
  ├── QIDO-RS → cari studi (GET /studies)
  ├── STOW-RS → upload gambar (POST /studies)
  ├── WADO-RS → ambil studi (GET /studies/{uid})
  ├── MWL-RS  → kelola worklist (GET/POST /mwl/workitems)
  │
  ▼
PACS (DCM4CHEE arc)
  │
  ├── DICOM C-STORE ← terima gambar dari modalitas
  ├── DICOM C-FIND  → layani query MWL
  ├── DICOM C-MOVE  → kirim studi ke modality/workstation
  │
  ▼
Database (PostgreSQL)  +  Storage (filesystem)
  │
  ▼
Keycloak (autentikasi OIDC untuk REST API)
```

### Alur kerja lengkap:

1. **Order entry** — Petugas radiologi buat jadwal pemeriksaan di Portal (Filament Registration)
2. **MWL tersimpan** — Portal POST ke MWL-RS endpoint, data masuk ke worklist PACS
3. **Modalitas query MWL** — Alat (atau simulator) C-FIND MWL dari PACS, dapat data pasien
4. **Akuisisi & kirim** — Alat kirim gambar via C-STORE ke PACS
5. **Portal akses studi** — Dokter cari studi via QIDO-RS, lihat metadata, download/lihat via WADO-RS

---

## 3. Sizing Infrastruktur

### Asumsi: RS tipe B/C (~200-400 tempat tidur)

### Estimasi Volume Harian

| Modalitas | Studi/hari | Ukuran/studi | Total/hari |
|-----------|-----------|-------------|-----------|
| CT | 15-40 | 150-400 MB | ~2.25-16 GB |
| MRI | 10-25 | 80-250 MB | ~0.8-6.25 GB |
| X-ray digital | 80-200 | 10-25 MB | ~0.8-5 GB |
| USG | 20-50 | 15-40 MB | ~0.3-2 GB |
| **Total** | **125-315** | | **~4-29 GB/hari** |

### Formula Sizing Storage

```
Storage/tahun = Σ(studi/hari × ukuran rata-rata × 365) × faktor retensi × faktor redundansi
```

- Retensi: minimal 5 tahun (Indonesia, rekam medis RS)
- Faktor redundansi (RAID/replikasi): 1.5-2x
- Estimasi: **15-40 TB/tahun** sebelum kompresi, tergantung mix modalitas

### Spesifikasi Server

| Komponen | Spesifikasi |
|----------|-------------|
| CPU | 8-16 core |
| RAM | 32-64 GB |
| Storage online (30-90 hari terakhir) | SSD/NVMe |
| Storage arsip | NAS/SAN dengan tiering otomatis |
| Network internal | Minimal 1 Gbps, ideal 10 Gbps antar PACS-storage-modalitas |

---

## 4. Persiapan Lokal Sebelum Terjun ke Lapangan

### Checklist Teknis

#### □ Jaringan & Infrastruktur
- [ ] IP statis untuk semua server PACS, database, portal
- [ ] VLAN terpisah untuk: (1) traffic DICOM alat-modalitas, (2) traffic REST API, (3) management
- [ ] Firewall: port 11112 (DICOM), 8080/8443 (REST), 8843 (Keycloak), 5432 (DB internal)
- [ ] Latency jaringan antara PACS dan storage < 1 ms (ideal) / < 5 ms (acceptable)
- [ ] DNS internal atau host file mapping untuk nama service

#### □ Server & Storage
- [ ] OS server terinstall (minimal Ubuntu 22.04 LTS / Debian 12)
- [ ] Docker Engine + Docker Compose plugin terinstall
- [ ] Storage ter-mount dan terverifikasi (filesystem, permission, quota)
- [ ] Backup storage destination siap (terpisah dari storage utama)

#### □ DCM4CHEE
- [ ] Docker compose pull sukses (semua image terdownload)
- [ ] Container start tanpa error — `docker compose up -d` → semua service healthy
- [ ] Keycloak realm dan client terkonfigurasi
- [ ] REST API bisa diakses: `GET /dcm4chee-arc/aets/DCM4CHEE/rs/monitoring/health` → 200
- [ ] Token Keycloak bisa didapat: `POST /realms/dcm4che/protocol/openid-connect/token` → 200
- [ ] DICOM C-ECHO dari mesin lokal ke PACS: `echoscu -aet TEST -aec DCM4CHEE {host} 11112` → sukses
- [ ] C-STORE test dari simulator/storescu: kirim 1 file DICOM → muncul di UI PACS
- [ ] MWL: buat workitem via REST → muncul di query C-FIND MWL

#### □ Portal (Laravel/Filament)
- [ ] Aplikasi Laravel bisa diakses via browser
- [ ] Login admin berfungsi
- [ ] Konfigurasi Server (host, port, AE title, credentials) tersimpan dan bisa test koneksi
- [ ] Registrasi pasien + buat MWL → data muncul di worklist simulator
- [ ] Study browser bisa mencari studi dari PACS
- [ ] Worklist sync bisa menarik data dari PACS

#### □ Simulator / Alat Uji
- [ ] Simulator bisa C-FIND MWL dari PACS → dapat worklist
- [ ] Simulator bisa C-STORE gambar ke PACS → studi muncul di Portal
- [ ] Simulator SCP bisa terima studi dari PACS (C-MOVE)

### Hal Paling Sering Terlewat

1. **Conformance testing dengan modalitas NYATA** — setiap vendor CT/MRI punya DICOM Conformance Statement berbeda (private tags, format kompresi, batasan ukuran). Usahakan pinjam unit test atau minta vendor untuk sesi uji jarak jauh sebelum H-1 instalasi.

2. **HL7 integration** — jika RS target sudah punya HIS/RIS yang bicara HL7 (kemungkinan besar untuk RS sedang-besar), sistem REST murni perlu **interface engine** seperti Mirth Connect (open-source) untuk menjembatani HL7 ↔ REST. Diskusikan sejak awal: apakah portal akan jadi satu-satunya entry point order, atau perlu integrasi dengan HIS eksisting.

3. **Backup & restore test** — jangan sampai ketahuan gagal pas produksi. Backup DB PACS + Keycloak, lalu restore di environment uji.

4. **Volume testing** — uji dengan ribuan studi sintetis sekaligus untuk lihat performa query dan rendering sebelum beban nyata masuk.

---

## 5. Topologi Jaringan Referensi

```
                    ┌──────────────────┐
                    │   Internet       │
                    │   (VPN/SSL)      │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │   Load Balancer  │
                    │   (nginx/haproxy)│
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
     ┌────────▼───┐  ┌──────▼──────┐  ┌───▼────────┐
     │  VLAN REST  │  │ VLAN DICOM  │  │ VLAN MGMT   │
     │  Portal +   │  │ Alat → PACS │  │ SSH + Admin │
     │  Web UI     │  │ port 11112  │  │             │
     └──────┬──────┘  └──────┬──────┘  └──────┬──────┘
            │                │                │
     ┌──────▼────────────────▼────────────────▼──────┐
     │                DOCKER HOST                      │
     │  ┌──────────┐  ┌──────────┐  ┌──────────────┐  │
     │  │   arc    │  │   db     │  │   keycloak   │  │
     │  │ REST+UI  │  │PostgreSQL│  │   OIDC Auth  │  │
     │  │ DICOM    │  │ pacsdb   │  │   MariaDB    │  │
     │  └────┬─────┘  └────┬─────┘  └──────┬───────┘  │
     │       │             │               │           │
     │  ┌────▼─────────────▼───────────────▼───────┐   │
     │  │         Docker Network (bridge)          │   │
     │  └──────────────────────────────────────────┘   │
     └─────────────────────────────────────────────────┘
```

---

## 6. Pertanyaan yang Perlu Ditanyakan ke RS Sebelum Instalasi

### Infrastruktur
- Apakah sudah ada server fisik/virtual untuk PACS? Spesifikasi?
- Apakah storage sudah tersedia? Kapasitas? RAID?
- Bagaimana topologi jaringan RS? Ada VLAN terpisah?
- Apakah ada firewall yang perlu dikonfigurasi?
- SLA uptime yang diharapkan? (24/7 atau jam kerja?)

### Modalitas
- Berapa jumlah dan jenis modalitas yang akan terhubung?
- AE title masing-masing modalitas? (perlu didaftarkan)
- Apakah modalitas sudah mendukung DICOM MWL?
- Vendor modalitas? Bisa dapat DICOM Conformance Statement?

### HIS/RIS Eksisting
- Apakah sudah ada sistem informasi RS (HIS/SIMRS)?
- Apakah RIS sudah ada atau perlu dibangun dari nol?
- Sistem eksisting bicara HL7 atau REST?
- Jika HL7: versi berapa? (2.3, 2.5, 2.5.1?)
- Jika sudah ada: perlukah integrasi, atau portal jadi sistem paralel?

### Regulasi
- Aturan retensi rekam medis radiologi di Indonesia? (Umumnya 5 tahun dewasa, 25 tahun anak)
- Siaja yang berwenang akses data? (RBAC requirements)
- Perlukah audit trail untuk kepatuhan?

---

> **Dibuat:** 2026-07-11
> **Tujuan:** Dokumentasi arsitektur, persiapan deployment, dan acuan diskusi dengan pihak RS
