# Catatan Aktivitas Pembangunan Portal Klinis Radiologi (RIS-lite)

> **Proyek:** Portal Klinis Radiologi berbasis Laravel/Filament + DCM4CHEE PACS
> **Lokasi:** `/mnt/DiskD/Projects/DCM4CHE/dcm4chee-files/`
> **Periode:** Juli 2026

---

## 1. Infrastruktur & Environment

### 1.1 DDEV Setup
- [x] Inisialisasi proyek Laravel dengan DDEV
- [x] Konfigurasi PHP 8.4 + nginx-fpm + MariaDB 11.8
- [x] URL: `https://laravel-dcm4chee.ddev.site`
- [x] Restore DDEV setelah migrasi direktori (`/mnt/DiskD/Projects/DCM4CHE/laravel-dcm4chee` → `dcm4chee-files/laravel-dcm4chee`)

### 1.2 DCM4CHEE PACS (Docker)
- [x] Docker Compose dengan 6 service: `ldap`, `mariadb`, `keycloak`, `db`, `arc`, `scp-server`
- [x] 3 varian compose: base (localhost), public (192.168.2.220), private (192.168.2.220)
- [x] Local PACS image: `dcm4chee-arc-psql:5.34.3` (non-secure)
- [x] Remote PACS: `103.147.236.138` — image `dcm4chee-arc-psql:5.34.3-secure`
- [x] Port: 8080 (HTTP REST), 8443 (HTTPS REST), 8843 (Keycloak), 11112 (DICOM), 389 (LDAP)
- [x] CORS: `Access-Control-Allow-Origin: *` (default)

### 1.3 Keycloak
- [x] Realm: `dcm4che`
- [x] Client `dcm4chee-arc-rs` (confidential) — untuk REST API PACS
- [x] Client `ohif-viewer` (public) — untuk OHIF Viewer SPA
- [x] Admin: `admin` / `changeit`

### 1.4 OHIF Viewer
- [x] Service di docker-compose: `ohif/app:latest` → port 3000
- [x] Config file: `docker/ohif/ohif-app-config.js`
- [x] CORS dari PACS sudah allow semua origin
- [x] Akses: `http://localhost:3000/`

---

## 2. Portal Laravel/Filament

### 2.1 Panel Configuration
- [x] `AdminPanelProvider` — konfigurasi panel Filament v4
- [x] Sidebar collapsible, database notifications, auth guard
- [x] Fix: `$navigationIcon` → `string|\BackedEnum|null`
- [x] Fix: `$navigationGroup` → `string|\UnitEnum|null`
- [x] Fix: `$view` non-static
- [x] Login: `admin@admin.com` / `admin123`

### 2.2 Filament Resources (CRUD)
| Resource | Group | Fitur |
|----------|-------|-------|
| ServerResource | Configuration | CRUD konfigurasi PACS (base_url, AET, credentials) |
| DeviceResource | Configuration | CRUD modalitas (AE title, server FK) |
| ProcedureResource | Configuration | CRUD katalog prosedur radiologi |
| WorklistItemResource | Operations | MWL queue, sync dari PACS MWL-RS |

### 2.3 Custom Pages
| Page | Fitur |
|------|-------|
| Dashboard | Statistik worklist, PACS health check, total studi/pasien |
| Registration | Form registrasi pasien + pembuatan MWL ke PACS |
| StudyBrowser | Pencarian studi dari PACS via QIDO-RS + tombol View (OHIF) |

### 2.4 Service Layer
- [x] `Client` — HTTP client ke REST API PACS (token management, retry 401)
- [x] `AuthService` — Keycloak OIDC token lifecycle (ambit, refresh, cache)
- [x] `StudyService` — QIDO-RS search, WADO-RS metadata/rendered/thumbnail
- [x] `PatientService` — CRUD pasien via REST API PACS
- [x] `DicomHelper` — Flatten DICOM JSON ke key-value, extract values
- [x] `AuditLog` — Logging setiap request ke PACS

### 2.5 Model Layer
- [x] `Server` — Konfigurasi server PACS (dengan encrypt credentials)
- [x] `Device` — Daftar modalitas/AE title
- [x] `Procedure` — Katalog prosedur radiologi
- [x] `WorklistItem` — MWL lokal (sinkronisasi dari PACS)
- [x] `User` — Admin user (Filament auth)

### 2.6 Perbaikan yang Dilakukan
- [x] `StudyService::search()` → tambah `DicomHelper::flattenStudies()` agar tabel menampilkan data DICOM dengan benar
- [x] `StudyBrowser.php` → tambah Action `view_ohif`, buka OHIF Viewer di tab baru
- [x] Hapus `viteTheme()` dari panel config (manifest path issue)
- [x] Migrasi DDEV dari path lama ke path baru setelah restruktur direktori

---

## 3. DICOM Modality Simulator

- [x] Python pynetdicom GUI simulator
- [x] Fitur: C-FIND MWL, C-STORE, SCP server (port 11113)
- [x] AE title: `SIMULATOR` / `SIMULATOR-SCP`
- [x] Jalankan: `.venv/bin/python main.py`
- [x] Config: `config.py` / `config.json`

---

## 4. Volum Test & Backup

### 4.1 Volume Test
- [x] Script: `scripts/test/generate_volume_test.py`
- [x] Generate studi sintetis (CT, MR, CR, US) via C-STORE
- [x] Hasil: 12 studi (388 slice) dalam 24.4 detik — rata-rata 2.03 detik/studi

### 4.2 Backup
- [x] Script: `scripts/backup/backup_pacs.sh`
- [x] Script: `scripts/backup/restore_test.sh`
- [x] Coverage: PostgreSQL (pacsdb) + MariaDB (Keycloak) + storage DICOM

---

## 5. Dokumentasi

| File | Isi |
|------|-----|
| `API-DOKUMENTASI.md` | Dokumentasi REST API DCM4CHEE (QIDO-RS, STOW-RS, WADO-RS, MWL-RS, dll) + cara jalankan simulator |
| `ARSITEKTUR-DAN-DEPLOYMENT.md` | Arsitektur e2e, sizing infrastruktur, checklist persiapan, topologi jaringan, pertanyaan untuk RS |
| `PERBANDINGAN-DOKUMEN-DAN-IMPLEMENTASI.md` | Analisis gap teori vs implementasi, per kategori risiko/prioritas |

---

## 6. Yang Belum Dibangun (Gap untuk RIS-lite)

### 6.1 Fitur Klinis
| Fitur | Status | Prioritas |
|-------|--------|-----------|
| Order Management (status tracking, scheduling) | ✅ | Tinggi |
| Reporting System (input laporan radiologi + finalize/amend) | ✅ | Tinggi |
| Report Display/PDF viewer (di Portal) | ❌ | Sedang |
| Patient Merge/Deduplication | ❌ | Sedang |
| Study Details view (series/instance list) | ✅ | Sedang |
| Worklist filter by modality + status | ✅ | Sedang |

### 6.2 RBAC & Keamanan
| Fitur | Status | Prioritas |
|-------|--------|-----------|
| RBAC (roles: admin, radiologist, radiographer, dokter) | ✅ | Tinggi |
| Study-level access control (siapa boleh lihat studi apa) | ❌ | Rendah |
| Audit log viewer di Portal | ❌ | Sedang |

### 6.3 Integrasi DICOM
| Fitur | Status | Prioritas |
|-------|--------|-----------|
| C-MOVE dari Portal ke workstation | ❌ | Rendah |
| MPPS tracking (performed procedure step) | ❌ | Rendah |
| Advanced MWL (scheduled station, SPS) | ❌ | Sedang |

### 6.4 Infrastruktur
| Fitur | Status | Prioritas |
|-------|--------|-----------|
| Web viewer built-in (link ke OHIF sudah ada) | ✅ | - |
| H2 fallback dinonaktifkan | ❌ | Sedang |
| Health monitoring dashboard | ❌ | Rendah |

---

## 7. Sesi 15 Juli 2026 — RBAC + Timeline + Report Flow

### 7.1 RBAC Custom Roles
- [x] **`RolesAndPermissionsSeeder`** diperbarui — menambahkan 3 custom role:
  - `radiologist`: View orders/patients/worklist, CRUD reports, view studies
  - `radiographer`: View orders/patients, CRUD worklist, view studies
  - `dokter`: View orders/patients/reports
- [x] Custom permissions `view_dashboard` dan `view_studies` dibuat manual (tidak digenerate oleh shield)
- [x] 3 seed user baru:
  - `radiologist@radiology.com` / `radiologist123` (role: radiologist)
  - `radiographer@radiology.com` / `radiographer123` (role: radiographer)
  - `dokter@hospital.com` / `dokter123` (role: dokter)

### 7.2 Order Timeline Component
- [x] `resources/views/components/order-timeline.blade.php` — visual timeline vertikal untuk ViewOrder page
- [x] Steps: Pending → Scheduled → In Progress → Completed → Reported
- [x] Status: lingkaran hijau untuk selesai, abu-abu untuk belum, "Current" label di step aktif
- [x] Cancelled: ditampilkan di bawah timeline dengan icon merah

### 7.3 Report Flow dari Order
- [x] Tombol "Buat Laporan" di ViewOrder → muncul jika status = completed/reported dan belum ada report terkait
- [x] Auto-fill: accession_number, study_instance_uid, radiologist_id
- [x] Setelah create → redirect ke halaman edit report

### 7.4 Migrate & Seed
- [x] `php artisan migrate:fresh --seed` — sukses, semua data terisi (4 users, 5 orders, 5 patients, 8 procedures, 4 devices)

---

## 8. Aktivitas Remote PACS (103.147.236.138)

- [x] Identifikasi server remote (`mini_pacs@103.147.236.138`)
- [x] Verifikasi SSH port 22 terbuka
- [x] SSH login dan cek status PACS (containers running: arc, keycloak, db, ldap, mariadb)
- [x] Port 11112, 8080, 8443, 8843 diblock MikroTik — hanya port 22 tembus
- [x] Kesimpulan: perlu SSH tunnel untuk akses DICOM/REST dari luar
- [x] Diputuskan pakai PACS lokal untuk development
