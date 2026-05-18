# DCM4CHEE + Portainer — Dokumentasi Lengkap

Dokumentasi ini mencakup instalasi, konfigurasi, dan pengelolaan **DCM4CHEE Archive 5.x** (PACS) dan **Portainer CE** menggunakan Podman di Linux.

---

## 1. DCM4CHEE Archive 5.x — PACS Server

### 1.1 Arsitektur

| Container | Image | Fungsi | Port |
|-----------|-------|--------|------|
| dcm4chee-ldap | slapd-dcm4chee:2.6.10-34.2 | Menyimpan konfigurasi (AE Titles, device) | 389 |
| dcm4chee-db | postgres-dcm4chee:17.4-34 | Menyimpan metadata DICOM | 5432 |
| dcm4chee-arc | dcm4chee-arc-psql:5.34.2 | Core PACS (Storage, Query/Retrieve) | 8080, 8443, 11112, 2762, 2575, 9990, 9993 |

### 1.2 Struktur Direktori

```
dcm4chee-training/
├── podman-up.sh                    # Start script (Podman native)
├── podman-down.sh                  # Stop script
├── podman-compose.level1.yml       # Podman Compose YAML
├── docker-compose.level1-basic.yml
├── docker-compose.level2-intermediate.yml
├── docker-compose.level3-advanced.yml
├── LEVEL-1-BASIC.md                # Training materials
├── LEVEL-2-INTERMEDIATE.md
├── LEVEL-3-ADVANCED.md
├── .env.template
├── config/
│   ├── grafana/
│   ├── logstash/
│   └── kibana/
├── backup/
│   ├── db/
│   └── archive/
└── certs/
```

### 1.3 Cara Pakai

**Start semua container:**
```bash
cd dcm4chee-training
./podman-up.sh
```

**Stop semua container:**
```bash
./podman-down.sh
```

**Stop + hapus semua data (destructive!):**
```bash
./podman-down.sh -v
```

**Cek status:**
```bash
podman ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

**Lihat log real-time:**
```bash
podman logs -f dcm4chee-arc
```

### 1.4 Akses

| Layanan | URL / Port | Keterangan |
|---------|-----------|------------|
| Web UI | http://localhost:8080/dcm4chee-arc/ui2/ | User interface DICOM viewer |
| REST API | http://localhost:8080/dcm4chee-arc/ | STOW-RS, WADO-RS, QIDO-RS |
| DICOM | localhost:11112 | Storage SCP, Query/Retrieve SCP |
| HL7 MLLP | localhost:2762 | ORM (MWL Orders) |
| HL7 MLLP | localhost:2575 | ADT (Patient Events) |
| PostgreSQL | localhost:5432 | Database metadata |
| LDAP | localhost:389 | Konfigurasi |
| WildFly Admin | https://localhost:9990 | Admin console (bisa diakses setelah login) |

**Kredensial default:**
- Admin login: `root` / `changeit`
- PostgreSQL: `pacs` / `pacs`
- LDAP admin: `cn=admin,dc=dcm4che,dc=org` / `secret`
- AE Title: `DCM4CHEE`
- DICOM port: `11112`

### 1.5 Catatan Penting

- **First startup butuh 3-10 menit** — WildFly perlu deploy archive application pertama kali
- LDAP sering muncul `unhealthy` di awal — ini normal, konfigurasi tetap terbaca
- DICOM study disimpan di volume `dcm4chee_storage_data`
- Backup tersedia di `backup/db` dan `backup/archive`

---

## 2. Portainer CE — Container Management UI

### 2.1 Informasi Container

| Item | Detail |
|------|--------|
| Image | `docker.io/portainer/portainer-ce:lts` |
| Container name | `portainer` |
| Web UI | http://localhost:9000 |
| HTTPS UI | https://localhost:9443 |
| Tunnel | localhost:8000 |
| Data volume | `portainer_data` |
| Socket | `/var/run/docker.sock` → Podman socket |
| Auto-start | Systemd service |

### 2.2 Kredensial

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Dcm4chee2024!` |

### 2.3 Cara Pakai

**Start fresh:**
```bash
podman run -d -p 8000:8000 -p 9000:9000 -p 9443:9443 \
  --name portainer --restart=always --privileged \
  -v /run/user/1000/podman/podman.sock:/var/run/docker.sock:Z \
  -v portainer_data:/data:Z \
  docker.io/portainer/portainer-ce:lts
```

**Stop:**
```bash
systemctl --user stop container-portainer.service
podman stop portainer
```

**Hapus total (data hilang):**
```bash
podman rm -f portainer
podman volume rm portainer_data
systemctl --user stop container-portainer.service
```

### 2.4 Reset Password

Gunakan image helper resmi:
```bash
podman stop portainer && podman rm portainer
podman run --rm -v portainer_data:/data:Z \
  docker.io/portainer/helper-reset-password --password "PasswordBaru"
podman run -d -p 8000:8000 -p 9000:9000 -p 9443:9443 \
  --name portainer --restart=always --privileged \
  -v /run/user/1000/podman/podman.sock:/var/run/docker.sock:Z \
  -v portainer_data:/data:Z \
  docker.io/portainer/portainer-ce:lts
```

### 2.5 Systemd Auto-Start

Portainer menggunakan systemd user service untuk auto-start saat boot:
```bash
systemctl --user enable container-portainer.service
systemctl --user start container-portainer.service
systemctl --user status container-portainer.service
```

### 2.6 Fitur yang Bisa Dilakukan

- **Manage Containers** — Start, stop, restart, inspect container DCM4CHEE
- **View Logs** — Lihat log real-time dari web UI
- **Container Stats** — CPU, memory, network usage monitoring
- **Volumes** — Lihat dan manage persistent volumes
- **Networks** — Lihat network bridge antar container
- **Images** — Pull, remove, inspect Docker/Podman images

---

## 3. Troubleshooting

### 3.1 Port 8080 / 5432 sudah dipakai

```bash
# Cek siapa yang pakai
ss -tlnp | grep -E "8080|5432"

# Kill rootlessport (jika ada proses zombie)
kill $(fuser 8080/tcp 2>/dev/null)

# Atau container phpmyadmin pakai port 8080
podman stop lerd-phpmyadmin
```

### 3.2 DCM4CHEE Archive tidak bisa start (container "Created")

```bash
# Cek log
podman logs dcm4chee-arc --tail 50

# Restart ulang semua
cd dcm4chee-training && ./podman-down.sh -v && ./podman-up.sh
```

### 3.3 Podman: short-name tidak resolve

Gunakan prefix `docker.io/`:
```bash
# Salah ✗
podman run portainer/portainer-ce:lts

# Benar ✓
podman run docker.io/portainer/portainer-ce:lts
```

### 3.4 Portainer "Access denied" setelah reset password

```bash
# Hapus database lama dan rebuild fresh
podman stop portainer && podman rm portainer
podman volume rm portainer_data
podman run -d -p 8000:8000 -p 9000:9000 -p 9443:9443 \
  --name portainer --restart=always --privileged \
  -v /run/user/1000/podman/podman.sock:/var/run/docker.sock:Z \
  -v portainer_data:/data:Z \
  docker.io/portainer/portainer-ce:lts
```
Kemudian akses http://localhost:9000 dan buat user admin di halaman setup pertama.

---

## 4. Referensi Cepat

### Daftar Semua Container

```bash
podman ps -a --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

### Semua Volume

```bash
podman volume ls
```

### Semua Image

```bash
podman images
```

### Semua Network

```bash
podman network ls
```
# dcm4chee-guidelines
