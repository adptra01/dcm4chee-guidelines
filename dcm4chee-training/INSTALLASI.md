# Panduan Instalasi DCM4CHEE Archive 5.x
## Docker & Podman — Level 1, 2, 3

---

## Daftar Isi

1. [Prasyarat Sistem & Alat yang Dibutuhkan](#1-prasyarat-sistem--alat-yang-dibutuhkan)
2. [Cara Memilih: Docker atau Podman?](#2-cara-memilih-docker-atau-podman)
3. [Persiapan Awal (Semua Level & Runtime)](#3-persiapan-awal-semua-level--runtime)
4. [Level 1 — Basic (RS Kecil, 3 Container)](#4-level-1--basic-rs-kecil-3-container)
   - [via Docker Compose](#41-via-docker-compose)
   - [via Podman Compose](#42-via-podman-compose)
   - [via Podman Manual Script (podman-up.sh)](#43-via-podman-manual-script-podman-upsh)
   - [Verifikasi Level 1](#44-verifikasi-level-1)
5. [Level 2 — Intermediate (RS Menengah, ~7 Container)](#5-level-2--intermediate-rs-menengah-7-container)
   - [via Docker Compose](#51-via-docker-compose)
   - [via Podman Compose](#52-via-podman-compose)
   - [Verifikasi Level 2](#53-verifikasi-level-2)
6. [Level 3 — Advanced (RS Rujukan, ~11 Container)](#6-level-3--advanced-rs-rujukan-11-container)
   - [via Docker Compose](#61-via-docker-compose)
   - [Persiapan SSL Certificate](#62-persiapan-ssl-certificate)
   - [Verifikasi Level 3](#63-verifikasi-level-3)
7. [Akses & Kredensial Default](#7-akses--kredensial-default)
8. [Mengelola Container (Docker vs Podman)](#8-mengelola-container-docker-vs-podman)
9. [Troubleshooting Instalasi](#9-troubleshooting-instalasi)

---

## 1. Prasyarat Sistem & Alat yang Dibutuhkan

### Hardware Minimum

| Item | Level 1 | Level 2 | Level 3 |
|------|---------|---------|---------|
| RAM | 4 GB | 8 GB | 16-32 GB |
| CPU | 2 core | 4 core | 8 core |
| Disk OS | 20 GB | 40 GB | 80 GB |
| Disk Storage | 100 GB | 500 GB | 2 TB+ |
| Network | 1 Gbps | 1 Gbps | 10 Gbps |

### Software yang Harus Diinstall

#### Wajib (salah satu runtime):

| Alat | Minimal Versi | Cek Versi | Fungsi |
|------|--------------|-----------|--------|
| **Docker Engine** | 24.0+ | `docker --version` | Container runtime |
| **Docker Compose** | v2.20+ | `docker compose version` | Orchestrator multi-container |
| **Podman** | 4.0+ | `podman --version` | Container runtime alternatif |
| **Podman Compose** | 1.0+ | `podman compose version` | Orchestrator Podman |

> **Catatan:** Anda cukup install salah satu runtime (Docker ATAU Podman), tidak perlu keduanya.

#### Pendukung (disarankan):

| Alat | Minimal Versi | Cek | Fungsi |
|------|--------------|-----|--------|
| **cURL** | 7.68+ | `curl --version` | Test REST API (STOW-RS, QIDO-RS) |
| **netcat** | 0.7+ | `nc -h` | Kirim HL7 MLLP messages |
| **dcm4che tools** | 5.x | `echoscu --version` | Test DICOM C-ECHO, C-STORE |
| **jq** | 1.6+ | `jq --version` | Parse JSON output REST API |
| **OpenSSL** | 1.1+ | `openssl version` | Generate SSL certs (Level 3) |
| **Git** | 2.25+ | `git --version` | Clone repository |

### Install Tools Pendukung (Ubuntu/Debian)

```bash
sudo apt update && sudo apt install -y \
  curl netcat-openbsd jq openssl git
```

### Install dcm4che Tools (untuk test DICOM)

```bash
# Download dcm4che toolkit
cd /tmp
wget https://github.com/dcm4che/dcm4che/releases/download/5.34.0/dcm4che-5.34.0-bin.zip
unzip dcm4che-5.34.0-bin.zip
sudo mv dcm4che-5.34.0 /opt/dcm4che
sudo ln -sf /opt/dcm4che/bin/* /usr/local/bin/

# Test
echoscu --version
```

---

## 2. Cara Memilih: Docker atau Podman?

| Aspek | Docker | Podman |
|-------|--------|--------|
| **Rootless** | Perlu config tambahan | Default, lebih aman |
| **Systemd** | Docker daemon berjalan sebagai service system | Tidak perlu daemon, proses per-container |
| **Compose** | Bawaan Docker (`docker compose`) | Via plugin (`podman compose`) atau `podman-compose` |
| **Volume mount** | Standar | Perlu flag `:Z` atau `:z` untuk SELinux |
| **Network** | bridge otomatis | Perlu create network manual atau `--net=host` |
| **Image registry** | Docker Hub default | Sama, tapi perlu `docker.io/` prefix untuk short name |
| **Cocok untuk** | Production / tim sudah familiar | Rootless / security-conscious / Red Hat ecosystem |

### Kapan Pakai Docker vs Podman?

```
Situasi                                    Pilihan
─────────────────────────────────────────────────────────────
Server Ubuntu/Debian, tim pakai Docker    → Docker
Server Red Hat/CentOS/Fedora              → Podman
Butuh rootless (no sudo)                  → Podman
Sudah ada infrastruktur Docker            → Docker
Single server, development                → Podman (lebih simple)
Multi-server, orchestration               → Docker (Swarm/K8s)
```

---

## 3. Persiapan Awal (Semua Level & Runtime)

### 3.1 Clone Repository

```bash
git clone <repo-url> /mnt/DiskD/Projects/DCM4CHE
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training
```

### 3.2 Siapkan File Environment

```bash
cp .env.template .env
```

Edit file `.env` sesuai kebutuhan. Minimal isi yang perlu diubah:

```bash
nano .env
# Setidaknya pastikan:
# LDAP_PASSWORD=secret
# POSTGRES_PASSWORD=pacs
```

> **PENTING:** LDAP password default image slapd-dcm4chee adalah `secret`. Jika ingin ganti, pastikan konsisten di semua service. Lihat catatan di .env.template untuk detailnya.

### 3.3 Periksa Port yang Dibutuhkan

```bash
# Cek port yang dipakai (semua level butuh port ini)
for port in 389 5432 8080 11112; do
  if ss -tlnp | grep -q ":$port "; then
    echo "[WARN] Port $port sudah dipakai"
  else
    echo "[OK]   Port $port tersedia"
  fi
done
```

Jika ada port conflict, stop service yang memakainya:

```bash
# Cari proses yang pakai port
sudo ss -tlnp | grep -E '8080|5432|389|11112'

# Hentikan (contoh port 8080 dipakai phpmyadmin)
podman stop lerd-phpmyadmin  # atau
sudo systemctl stop apache2
```

### 3.4 Buat Direktori Pendukung

```bash
mkdir -p backup/db backup/archive certs grafana/provisioning/datasources \
  grafana/dashboards logstash/pipeline db-config ohif-config
```

---

## 4. Level 1 — Basic (RS Kecil, 3 Container)

**Services:** OpenLDAP + PostgreSQL + DCM4CHEE Archive

### 4.1 via Docker Compose

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training

# Start semua service
docker compose -f docker-compose.level1-basic.yml up -d

# Lihat proses startup (tekan Ctrl+C setelah semua healthy)
docker compose -f docker-compose.level1-basic.yml logs -f

# Cek status
docker compose -f docker-compose.level1-basic.yml ps
```

**Contoh output yang diharapkan:**
```
NAME                IMAGE                                    STATUS
dcm4chee-ldap       dcm4che/slapd-dcm4chee:2.6.10-34.2      Up (healthy)
dcm4chee-db         dcm4che/postgres-dcm4chee:17.4-34        Up (healthy)
dcm4chee-arc        dcm4che/dcm4chee-arc-psql:5.34.2         Up (healthy)
```

### 4.2 via Podman Compose

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training

# Pastikan network Podman sudah ada
podman network create dcm4chee-net 2>/dev/null || true

# Start semua service
podman compose -f podman-compose.level1.yml up -d

# Cek status
podman compose -f podman-compose.level1.yml ps
```

### 4.3 via Podman Manual Script (podman-up.sh)

Script ini menjalankan container satu per satu tanpa compose — cocok untuk learning atau debug:

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training
chmod +x podman-up.sh
./podman-up.sh
```

**Yang dilakukan script secara berurutan:**

| Step | Action | Durasi |
|------|--------|--------|
| 1 | Create Podman network `dcm4chee-net` | < 1 detik |
| 2 | Create volumes (ldap, db, storage, wildfly) | < 1 detik |
| 3 | Pull images dari Docker Hub | 1-5 menit |
| 4 | Start LDAP container, tunggu healthy | 30-60 detik |
| 5 | Start PostgreSQL, tunggu healthy | 30-60 detik |
| 6 | Start DCM4CHEE Archive | < 1 detik |
| 7 | Tunggu Archive healthy | 3-10 menit (first run) |
| 8 | Tampilkan status & URL | selesai |

**Untuk stop:**
```bash
./podman-down.sh
# Atau manual:
podman stop dcm4chee-arc dcm4chee-db dcm4chee-ldap
```

### 4.4 Verifikasi Level 1

```bash
# 1. Cek health status semua container
docker ps --format "table {{.Names}}\t{{.Status}}"

# 2. Cek Web UI (harus return HTTP 200)
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/dcm4chee-arc/ui2/
# Output: 200

# 3. Test DICOM C-ECHO (ping PACS)
echoscu -b DCM4CHEE localhost 11112 -aet TEST_CLIENT -aec DCM4CHEE
# Output: Association accepted, Result: 0x0000 (Success)

# 4. Test REST API (QIDO-RS)
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies"
# Output: JSON array (kosong = wajar, belum ada study)

# 5. Buka browser
# URL: http://localhost:8080/dcm4chee-arc/ui2
# User: root
# Pass: secret
```

---

## 5. Level 2 — Intermediate (RS Menengah, ~7 Container)

**Services tambahan dari Level 1:**
- Grafana (monitoring dashboard)
- OHIF Viewer (web DICOM viewer)
- Backup Scheduler (auto backup DB & storage)

### 5.1 via Docker Compose

Level 2 menggunakan **multi-file compose** — file Level 1 jadi base, file Level 2 menambahkan service baru:

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training

# Opsi A: Mulai dari Level 1 dulu, lalu tambah Level 2
docker compose -f docker-compose.level1-basic.yml up -d
docker compose -f docker-compose.level1-basic.yml \
  -f docker-compose.level2-intermediate.yml up -d

# Opsi B: Langsung semua (rekomendasi)
docker compose -f docker-compose.level2-intermediate.yml up -d
```

> **Catatan:** File `docker-compose.level2-intermediate.yml` menggunakan `extends:` untuk mewarisi service LDAP, DB, dan Archive dari Level 1. Service tambahan (Grafana, OHIF, Backup) didefinisikan langsung.

### 5.2 via Podman Compose

Podman compose untuk Level 2 belum tersedia sebagai file terpisah. Solusinya:

```bash
# Gunakan multi-file dengan podman compose
podman compose -f podman-compose.level1.yml \
  -f docker-compose.level2-intermediate.yml up -d
```

Atau jalankan container tambahan secara manual:

```bash
# Grafana
podman run -d --name dcm4chee-grafana --network dcm4chee-net \
  -p 3000:3000 \
  -e GF_SECURITY_ADMIN_PASSWORD=admin123 \
  docker.io/grafana/grafana:11.4.0

# OHIF Viewer
podman run -d --name dcm4chee-ohif --network dcm4chee-net \
  -p 3001:3000 \
  docker.io/ohif/viewer:latest
```

### 5.3 Verifikasi Level 2

```bash
# 1. Semua container berjalan
docker ps --format "table {{.Names}}\t{{.Status}}"

# 2. Grafana
curl -s -o /dev/null -w "%{http_code}" http://localhost:3000
# Output: 200 (atau 302 redirect ke login)

# 3. OHIF Viewer
curl -s -o /dev/null -w "%{http_code}" http://localhost:3001
# Output: 200

# 4. Backup scheduler
docker logs dcm4chee-backup --tail 10

# 5. Akses:
# - Archive Web UI: http://localhost:8080/dcm4chee-arc/ui2
# - Grafana:        http://localhost:3000  (admin / admin123)
# - OHIF Viewer:    http://localhost:3001
```

---

## 6. Level 3 — Advanced (RS Rujukan, ~11 Container)

**Services tambahan dari Level 1:**
- Keycloak (SSO authentication)
- OAuth2 Proxy (security gateway)
- Elasticsearch + Logstash + Kibana (ELK monitoring)
- Grafana (metrics dashboard)
- Backup Manager (enhanced backup)

### 6.1 via Docker Compose

Level 3 adalah **standalone file** — tidak mewarisi dari Level 1/2:

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training

# Start full production stack
docker compose -f docker-compose.level3-advanced.yml up -d

# Monitor startup (ini bisa makan waktu 5-15 menit)
docker compose -f docker-compose.level3-advanced.yml logs -f

# Cek status
docker compose -f docker-compose.level3-advanced.yml ps
```

### 6.2 Persiapan SSL Certificate

Level 3 menggunakan HTTPS/SSL. Generate self-signed certificate untuk testing:

```bash
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training/certs

# Generate self-signed certificate
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout key.pem -out cert.pem \
  -days 365 \
  -subj "/CN=dcm4chee.local/O=DCM4CHEE/C=ID"

# Cek file terbuat
ls -la cert.pem key.pem
```

> **Untuk production:** Ganti self-signed cert dengan certificate dari CA resmi (Let's Encrypt, atau CA internal RS).

### 6.3 Verifikasi Level 3

```bash
# 1. Tunggu semua container healthy (bisa 10-15 menit)
watch docker compose -f docker-compose.level3-advanced.yml ps

# 2. Cek Keycloak
curl -sk -o /dev/null -w "%{http_code}" https://localhost:8843
# Output: 200

# 3. Cek Elasticsearch
curl -s http://localhost:9200/_cluster/health | jq .status
# Output: "green" atau "yellow"

# 4. Cek Kibana (via OAuth2 proxy)
curl -sk -o /dev/null -w "%{http_code}" https://localhost:8843/kibana
# Output: 200 (atau 302 redirect ke login)

# 5. Cek Grafana
curl -s -o /dev/null -w "%{http_code}" http://localhost:8300
# Output: 200

# 6. Akses:
# - Archive UI:   https://localhost:8443/dcm4chee-arc/ui2 (via Keycloak SSO)
# - Keycloak:      https://localhost:8843/admin  (root / [KEYCLOAK_PASSWORD])
# - Kibana:        https://localhost:8843/kibana
# - Grafana:       http://localhost:8300  (admin / [GRAFANA_PASSWORD])
# - Elasticsearch:  http://localhost:9200
```

---

## 7. Akses & Kredensial Default

### Level 1 & 2

| Service | URL / Port | User | Password |
|---------|-----------|------|----------|
| Archive Web UI | http://localhost:8080/dcm4chee-arc/ui2 | root | secret |
| WildFly Console | http://localhost:9990 | root | secret |
| REST API | http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs | - | - |
| DICOM | localhost:11112 | AE: DCM4CHEE | - |
| PostgreSQL | localhost:5432 | pacs | pacs |
| OpenLDAP | localhost:389 | cn=admin,dc=dcm4che,dc=org | secret |
| Grafana (L2) | http://localhost:3000 | admin | admin123 |
| OHIF Viewer (L2) | http://localhost:3001 | - | - |

### Level 3

| Service | URL / Port | User | Password |
|---------|-----------|------|----------|
| Archive UI (Secured) | https://localhost:8443/dcm4chee-arc/ui2 | Keycloak SSO | via Keycloak |
| Keycloak Admin | https://localhost:8843/admin | root | isi di .env |
| Kibana (via Proxy) | https://localhost:8843/kibana | Keycloak SSO | via Keycloak |
| Grafana | http://localhost:8300 | admin | isi di .env |
| Elasticsearch | http://localhost:9200 | - | - |
| WildFly Console | http://localhost:9990 | root | secret |
| DICOM | localhost:11112 | AE: DCM4CHEE | - |

---

## 8. Mengelola Container (Docker vs Podman)

### Operasi Dasar

| Operasi | Docker | Podman |
|---------|--------|--------|
| Lihat container | `docker ps` | `podman ps` |
| Lihat logs | `docker logs -f dcm4chee-arc` | `podman logs -f dcm4chee-arc` |
| Masuk container | `docker exec -it dcm4chee-arc bash` | `podman exec -it dcm4chee-arc bash` |
| Stop container | `docker stop dcm4chee-arc` | `podman stop dcm4chee-arc` |
| Restart container | `docker restart dcm4chee-arc` | `podman restart dcm4chee-arc` |

### Compose Operations

| Operasi | Docker Compose | Podman Compose |
|---------|---------------|----------------|
| Start semua | `docker compose -f <file> up -d` | `podman compose -f <file> up -d` |
| Stop semua | `docker compose -f <file> down` | `podman compose -f <file> down` |
| Lihat status | `docker compose -f <file> ps` | `podman compose -f <file> ps` |
| Lihat logs | `docker compose -f <file> logs -f` | `podman compose -f <file> logs -f` |
| Hapus + data | `docker compose -f <file> down -v` | `podman compose -f <file> down -v` |

### Perintah Spesifik Podman

```bash
# Rootless: jalankan tanpa sudo
podman ps

# Health check inspect
podman healthcheck inspect dcm4chee-arc

# Podman volumes
podman volume ls
podman volume inspect dcm4chee_storage_data
```

---

## 9. Troubleshooting Instalasi

### 9.1 Container ARC Tidak Mau Start (Status: Created / Exited)

```bash
# 1. Cek apakah LDAP dan DB sudah healthy
docker inspect dcm4chee-ldap --format '{{.State.Health.Status}}'
docker inspect dcm4chee-db --format '{{.State.Health.Status}}'

# 2. Cek log archive
docker logs dcm4chee-arc --tail 50

# 3. Cari error seperti "LDAP connection refused" atau "DB connection refused"
#    Ini artinya ARC start sebelum LDAP/DB siap

# 4. Restart arc setelah LDAP dan DB healthy
docker restart dcm4chee-arc
```

### 9.2 Web UI Error 500 — "ARCHIVE_DEVICE_NAME mismatch"

**Penyebab:** Variable `ARCHIVE_DEVICE_NAME` di container tidak sama dengan device name yang di-bootstrap di LDAP.

**Solusi:** Hapus `ARCHIVE_DEVICE_NAME` dari environment container. Biarkan archive menggunakan device name default dari LDAP (`dcm4chee-arc`).

```bash
# Cek device name di LDAP
docker exec dcm4chee-ldap ldapsearch -x -b "dicomDeviceName=dcm4chee-arc,cn=Devices,cn=DCM4CHEE,cn=Config,dc=dcm4che,dc=org"

# Seharusnya sudah ada entry untuk dcm4chee-arc (default bootstrap slapd)
```

### 9.3 C-ECHO Gagal / Association Rejected

```bash
# Penyebab 1: Calling AE belum terdaftar
# Solusi: Buka Web UI → Configuration → Network → Network AE
#         Tambahkan AE title client Anda

# Penyebab 2: Port 11112 tidak terbuka
sudo ss -tlnp | grep 11112
# Harus ada output: LISTEN ... docker-proxy atau podman

# Penyebab 3: Firewall block
sudo ufw status
sudo ufw allow 11112/tcp
```

### 9.4 LDAP Container Unhealthy

```bash
# Cek health check detail
docker inspect dcm4chee-ldap --format '{{.State.Health}}'

# Cek log LDAP
docker logs dcm4chee-ldap --tail 30

# Penyebab umum: password mismatch antara health check dan LDAP config
# Default password slapd-dcm4chee image = "secret"
# Pastikan health check pakai: -w secret (bukan -w changeit)

# Restart LDAP
docker restart dcm4chee-ldap
```

### 9.5 Port Conflict

```bash
# Cek port
sudo ss -tlnp | grep -E '8080|5432|389|11112'

# Jika ada service lain yang pakai port tersebut:
sudo systemctl stop apache2    # jika pakai port 80/8080
podman stop lerd-phpmyadmin    # jika phpmyadmin pakai 8080

# Atau ganti port di docker-compose.yml (kurang direkomendasikan)
# Ubah "8080:8080" menjadi "8081:8080"
```

### 9.6 Podman: "short-name" error

```bash
# Error: short-name "portainer/portainer-ce" did not resolve
# Solusi: gunakan prefix docker.io/
podman pull docker.io/dcm4che/dcm4chee-arc-psql:5.34.2
```

### 9.7 Semua Container Gagal Start Setelah Reboot

```bash
# Docker: enable auto-start
sudo systemctl enable docker
sudo systemctl start docker

# Podman: enable lingering for user
sudo loginctl enable-linger $(whoami)

# Restart container
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training
./podman-up.sh  # atau
podman compose -f podman-compose.level1.yml start
```

### 9.8 Reset Total (Semua Data Hilang)

```bash
# Docker
cd /mnt/DiskD/Projects/DCM4CHE/dcm4chee-training
docker compose -f docker-compose.level1-basic.yml down -v

# Podman
./podman-down.sh -v

# Hapus semua volume yang tersisa
docker volume prune -f  # atau
podman volume prune -f
```

---

## Lampiran: Tabel Image Docker

| Image | Version | Size | Level |
|-------|---------|------|-------|
| `dcm4che/slapd-dcm4chee` | 2.6.10-34.2 | ~150 MB | 1, 2, 3 |
| `dcm4che/postgres-dcm4chee` | 17.4-34 | ~400 MB | 1, 2, 3 |
| `dcm4che/dcm4chee-arc-psql` | 5.34.2 | ~1.2 GB | 1, 2 |
| `dcm4che/dcm4chee-arc-psql` | 5.34.2-secure | ~1.3 GB | 3 |
| `grafana/grafana` | 11.4.0 | ~350 MB | 2, 3 |
| `ohif/viewer` | latest | ~500 MB | 2 |
| `dcm4che/keycloak` | 26.0.6 | ~400 MB | 3 |
| `dcm4che/oauth2-proxy` | 7.7.1 | ~100 MB | 3 |
| `elasticsearch` | 8.15.1 | ~1.5 GB | 3 |
| `dcm4che/logstash-dcm4chee` | 8.15.1-18 | ~1.0 GB | 3 |
| `kibana` | 8.15.1 | ~1.2 GB | 3 |

**Total download size per level:**
- Level 1: ~1.8 GB
- Level 2: ~2.6 GB (Level 1 + Grafana + OHIF)
- Level 3: ~7.2 GB (Level 1 + Keycloak + OAuth2 + ELK + Grafana + Logstash)

---

## Lampiran: Cek Cepat Status Sistem

```bash
#!/bin/bash
# Script: cek-dcm4chee.sh
# Simpan dan jalankan: bash cek-dcm4chee.sh

echo "=== DCM4CHEE Health Check ==="
echo ""

# Cek container runtime
if command -v docker &>/dev/null; then
  RUNTIME="docker"
elif command -v podman &>/dev/null; then
  RUNTIME="podman"
else
  echo "[ERROR] No container runtime found!"
  exit 1
fi
echo "Runtime: $RUNTIME"

# Cek container
echo ""
echo "--- Container Status ---"
$RUNTIME ps --format "table {{.Names}}\t{{.Status}}" 2>/dev/null || echo "No containers running"

# Cek port
echo ""
echo "--- Port Check ---"
for port in 389 5432 8080 11112; do
  if ss -tlnp | grep -q ":$port "; then
    echo "[OK] Port $port"
  else
    echo "[--] Port $port (not listening)"
  fi
done

# Cek Web UI
echo ""
echo "--- Web UI ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/dcm4chee-arc/ui2/ 2>/dev/null)
if [ "$HTTP_CODE" = "200" ]; then
  echo "[OK] Web UI: HTTP $HTTP_CODE"
else
  echo "[--] Web UI: HTTP $HTTP_CODE (not ready)"
fi

# Cek DICOM
echo ""
echo "--- DICOM ---"
if command -v echoscu &>/dev/null; then
  echoscu -b TEST localhost 11112 -aet TEST -aec DCM4CHEE 2>&1 | grep -q "Accepted"
  if [ $? -eq 0 ]; then
    echo "[OK] DICOM C-ECHO: Accepted"
  else
    echo "[--] DICOM C-ECHO: Failed"
  fi
else
  echo "[--] echoscu not installed, skip DICOM check"
fi

echo ""
echo "=== Done ==="
```

---

*Dokumentasi instalasi ini untuk DCM4CHEE Archive 5.34.2*
*Runtime: Docker 24+ / Podman 4+*
*OS Target: Ubuntu 22.04/24.04 LTS*
*Terakhir diperbarui: Mei 2026*
