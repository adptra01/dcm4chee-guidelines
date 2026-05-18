# LEVEL 3: ADVANCED — Production-Ready DCM4CHEE Implementation

## Prasyarat

Level ini menuntut pemahaman mendalam tentang:
- [ ] DCM4CHEE Archive 5.x deployment dan konfigurasi dasar (Level 1-2)
- [ ] PostgreSQL administration
- [ ] Docker/Docker Compose networking dan volume management
- [ ] Dasar networking, firewall, dan SSL/TLS
- [ ] Backup/recovery concepts
- [ ] Linux system administration (Ubuntu 22.04 LTS)

## Tujuan Pembelajaran

Setelah menyelesaikan level ini, Anda akan mampu:
1. Mengimplementasikan TLS/SSL untuk DICOM dan Web communications
2. Mengatur Keycloak SSO untuk secure access
3. Mendesain dan mengimplementasikan Backup & Disaster Recovery strategy
4. Mengkonfigurasi High Availability clustering
5. Mengoptimasi performance untuk production load (> 500 study/hari)
6. Mengimplementasikan advanced monitoring dengan Elastic Stack
7. Memahami compliance requirements (audit trail, HIPAA/FHIR)
8. Melakukan migrasi dari PACS lama ke DCM4CHEE

---

## Modul 1: Security Implementation

### 1.1 Security Overview

DCM4CHEE 5.x menyediakan beberapa layer keamanan:

```
┌────────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                             │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Layer 1: Network Security                                      │
│  ├── Firewall (iptables/nftables)                              │
│  ├── Network segmentation (VLAN)                              │
│  └── Port access control │
│                                                                 │
│  Layer 2: Transport Security                                    │
│  ├── TLS/SSL untuk Web (HTTPS)                                 │
│  ├── TLS untuk DICOM (DICOM TLS)                               │
│  └── mTLS untuk inter-service communication                     │
│                                                                 │
│  Layer 3: Application Security                                  │
│  ├── Keycloak SSO Authentication                               │
│  ├── Role-Based Access Control (RBAC)                         │
│  ├── AE Title authentication (DICOM)                          │
│  └── HL7 authentication                                         │
│                                                                 │
│  Layer 4: Data Security                                         │
│  ├── Encryption at-rest (storage)                              │
│  ├── Data integrity (MD5/SHA-256 checksum)                    │
│  └── Audit trail (IHE ATNA compliant)                          │
│                                                                 │
│  Layer 5: Access Control                                        │
│  ├── LDAP user management                                      │
│  ├── Archive role permissions                                  │
│  └── Per-AE access restrictions                                 │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 1.2 Secured Deployment dengan Keycloak SSO

**Arsitektur Secured Setup:**

```
┌─────────────────────────────────────────────────────────────────┐
│                  SECURED DCM4CHEE ARCHITECTURE                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              External Clients                           │  │
│  │  Browser ──── HTTPS (443) ──── Nginx/Reverse Proxy     │  │
│  │  Modality ──── DICOM TLS (11112) ──── Archive          │  │
│  └────────────────────────┬───────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼───────────────────────────────┐  │
│  │            OAuth2 Proxy (nginx-based)                 │  │
│  │  - Authenticates user via Keycloak                     │  │
│  │  - Acts as reverse proxy to Archive UI                │  │
│  │  - Protects Kibana access                             │  │
│  └────────────────────────┬───────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼───────────────────────────────┐  │
│  │           Keycloak (Auth Server)                        │  │
│  │  Port: 12575 (OAuth2 Proxy: 8843)                      │  │
│  │  - User authentication                                 │  │
│  │  - Role assignment (root/admin/user)                   │  │
│  │  - Token issuance (JWT)                               │  │
│  └────────────────────────┬───────────────────────────────┘  │
│                           │                                     │
│  ┌────────────────────────▼───────────────────────────────┐  │
│  │              DCM4CHEE Archive                          │  │
│  │  Secure variant: dcm4chee-arc-psql:X.X.X-secure       │  │
│  │  - Requires valid JWT token for UI access             │  │
│  │  - REST API requires authentication                    │  │
│  │  - DICOM services tetap tanpa TLS (konfigurasi)       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.3 Step-by-Step Secured Deployment

**docker-compose-secured.yml:**

```yaml
version: "3.8"

services:
  ldap:
    image: dcm4che/slapd-dcm4chee:2.6.10-34.2
    container_name: dcm4chee-ldap
    environment:
      SLAPD_PASSWORD: ${LDAP_PASSWORD}
      SLAPD_DOMAIN: dcm4che.org
      SLAPD_ORGANIZATION: dcm4che
    ports:
      - "389:389"
    volumes:
      - ./ldap:/var/lib/openldap/openldap-data
      - ./slapd.d:/etc/openldap/slapd.d
    healthcheck:
      test: ["CMD", "ldapwhoami", "-x", "-H", "ldap://localhost", "-D", "cn=admin,dc=dcm4che,dc=org", "-w", "${LDAP_PASSWORD}"]
      interval: 30s
      timeout: 10s
      retries: 5
    restart: unless-stopped

  db:
    image: dcm4che/postgres-dcm4chee:17.4-34
    container_name: dcm4chee-db
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    ports:
      - "5432:5432"
    volumes:
      - ./db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U pacs -d pacsdb"]
      interval: 30s
      timeout: 10s
      retries: 5
    restart: unless-stopped

  keycloak:
    image: dcm4che/keycloak:26.0.6
    container_name: dcm4chee-keycloak
    environment:
      KEYCLOAK_ADMIN: root
      KEYCLOAK_ADMIN_PASSWORD: ${KEYCLOAK_PASSWORD}
      KC_DB_URL: postgresql://db:5432/keycloak
      KC_DB_USERNAME: pacs
      KC_DB_PASSWORD: ${DB_PASSWORD}
      KC_HOSTNAME_STRICT: "false"
      KC_HTTP_ENABLED: "true"
    ports:
      - "12575:8080"
    volumes:
      - ./keycloak/data:/opt/keycloak/data
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

  oauth2-proxy:
    image: dcm4che/oauth2-proxy:7.7.1
    container_name: dcm4chee-oauth2-proxy
    environment:
      OAUTH2_PROXY_PROVIDER: oidc
      OAUTH2_PROXY_OIDC_ISSUER_URL: https://keycloak:8843/realms/dcm4che
      OAUTH2_PROXY_CLIENT_ID: kibana
      OAUTH2_PROXY_CLIENT_SECRET: ${OAUTH2_CLIENT_SECRET}
      OAUTH2_PROXY_REDIRECT_URL: https://localhost:8843/oauth2/callback
      OAUTH2_PROXY_UPSTREAMS: http://arc:8080
      OAUTH2_PROXY_HTTP_ADDRESS: "0.0.0.0:4180"
      OAUTH2_PROXY_SSL_CERT_FILE: /etc/certs/cert.pem
      OAUTH2_PROXY_SSL_KEY_FILE: /etc/certs/key.pem
    ports:
      - "8843:4180"
    volumes:
      - ./certs:/etc/certs:ro
    depends_on:
      - keycloak
      - arc
    restart: unless-stopped

  arc:
    image: dcm4che/dcm4chee-arc-psql:5.34.2-secure
    container_name: dcm4chee-arc
    environment:
      POSTGRES_DB: pacsdb
      POSTGRES_USER: pacs
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      AUTH_SERVER_URL: https://keycloak:8843
      UI_AUTH_SERVER_URL: https://localhost:8843
      WILDFLY_CHOWN: /storage
      WILDFLY_WAIT_FOR: ldap:389 db:5432
      WILDFLY_JAVA_OPTS: "-Xms2g -Xmx6g -XX:+UseG1GC"
    ports:
      - "8080:8080"
      - "8443:8443"
      - "9990:9990"
      - "9993:9993"
      - "11112:11112"
      - "2762:2762"
      - "2575:2575"
    volumes:
      - ./wildfly:/opt/wildfly/standalone
      - ./storage:/storage
      - ./certs:/etc/x509
    depends_on:
      ldap:
        condition: service_healthy
      db:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-kf", "-H", "Authorization: Bearer test", "http://localhost:8080/dcm4chee-arc/ui2/"]
      interval: 60s
      timeout: 30s
      retries: 10
      start_period: 300s
    restart: unless-stopped

volumes:
  ldap_data:
  db_data:
  keycloak_data:
  wildfly_data:
  storage_data:
```

**Generate SSL certificates:**

```bash
mkdir -p /opt/dcm4chee-arc/certs
cd /opt/dcm4chee-arc/certs

# Generate self-signed certificate (for testing)
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout key.pem -out cert.pem \
  -subj "/CN=dcm4chee.local/O=DCM4CHEE/C=ID" \
  -addext "subjectAltName=DNS:dcm4chee.local,DNS:localhost,IP:127.0.0.1"

# For production: use Let's Encrypt or official CA
# certbot --nginx -d archive.rumahsakit.com

# Generate Java truststore for DCM4CHEE
keytool -importcert -file cert.pem -keystore dcm4chee-truststore.jks \
  -storepass changeit -noprompt
```

**Setup .env file:**

```bash
cat > /opt/dcm4chee-arc/.env << 'EOF'
# Passwords - GANTI untuk production!
LDAP_PASSWORD=GantiPasswordLdap_2025!
DB_PASSWORD=GantiPasswordDb_2025!
KEYCLOAK_PASSWORD=GantiPasswordKC_2025!
OAUTH2_CLIENT_SECRET=GantiSecretClient_2025!

# Archive settings
ARCHIVE_DEVICE_NAME=DCM4CHEE
ARCHIVE_STORAGE_DIR=/storage
EOF

chmod 600 /opt/dcm4chee-arc/.env
```

**Start secured deployment:**

```bash
cd /opt/dcm4chee-arc
docker compose -f docker-compose-secured.yml up -d

# Monitor Keycloak startup
docker logs -f dcm4chee-keycloak --since 60s
# Tunggu: "Keycloak X started in Xs"

# Verifikasi Keycloak
curl -k https://localhost:12575/health/ready

# Verifikasi OAuth2 Proxy
curl -I https://localhost:8843/

# Verifikasi Archive UI (harus redirect ke Keycloak)
curl -I https://localhost:8843/dcm4chee-arc/ui2/
```

### 1.4 Keycloak Configuration

**Login ke Keycloak Admin Console:**

```
URL: https://server:12575/admin
User: root
Password: [KEYCLOAK_PASSWORD dari .env]
```

**Langkah-langkah setup:**

**1. Verify/Import dcm4che Realm:**

DCM4CHEE secure image sudah include realm `dcm4che` dengan client `dcm4chee-arc-ui`. Cek di **Configure → Realm Settings → General** → Realm name: `dcm4che`.

**2. Create Users:**

1. **Configure → Users → Add User**
2. Isi:
   - Username: `radiolog1`
   - Email: `radiolog1@rumahsakit.com`
   - First Name: `Dr. Budi`
   - Last Name: `Santoso`
3. Klik **Save**
4. Tab **Credentials** → Set password
5. Tab **Role Mappings** → Assign roles:
   - `auth` (untuk login)
   - `user` (untuk akses viewer)

**3. Predefined Roles:**

| Role | Permissions |
|------|-------------|
| `auth` | Basic authentication |
| `user` | Viewer access, search studies |
| `root` | Full admin access |
| `auditlog` | View audit logs |
| `ADMINISTRATOR` | System configuration |

**4. Export/Import Realm Configuration:**

```bash
# Export realm
docker exec dcm4chee-keycloak /opt/keycloak/bin/kc.sh export \
  --realm dcm4che --users /tmp/users.json --file /tmp/dcm4che-realm.json

# Copy from container
docker cp dcm4chee-keycloak:/tmp/dcm4che-realm.json ./realm-config.json

# Import on new server
docker exec dcm4chee-keycloak /opt/keycloak/bin/kc.sh import \
  --realm /tmp/realm-config.json --force
```

### 1.5 DICOM TLS Configuration

**DICOM TLS** mengenkripsi komunikasi DICOM menggunakan TLS. Setiap AE title yang mau connect via TLS harus punya certificate.

**Konfigurasi TLS di DCM4CHEE:**

1. Buka **Configuration → Devices → DCM4CHEE → Network Configuration**
2. Aktifkan **TLS** untuk DICOM port:

| Setting | Value |
|---------|-------|
| `TLS` | true (untuk secure AE titles) |

3. Buka **Configuration → Network → Network AE → [AE_TITLE]**
4. Aktifkan **TLS** untuk AE tertentu:

| Setting | Value |
|---------|-------|
| `TLS` | true |
| `Trust Store` | /etc/x509/dcm4chee-truststore.jks |
| `Key Store` | /etc/x509/arc-cert.jks |
| `Password` | keystore password |

**Di sisi Modality (vendor config):**

Konfigurasi di sisi modalityvendor-specific. Umumnya:

```
AE Title: CT_SIEMENS_TLS
Protocol: DICOM TLS
TLS Version: 1.2+
Certificate: client.p12 (atau .pem + .key)
CA Certificate: ca-cert.pem
```

**Test DICOM TLS:**

```bash
# storescp dengan TLS
storescp +tls 11114 \
  -tls-cert cert.pem -tls-key key.pem \
  -tls-ca-cert ca-cert.pem \
  -aet TLS_TEST

# echoscu dengan TLS
echoscu +tls localhost 11112 \
  -aet MY_TLS_SC \
  -aec DCM4CHEE \
  -tls-cert cert.pem -tls-key key.pem \
  -tls-ca-cert ca-cert.pem
```

### 1.6 Encryption at Rest

**Metode 1: Filesystem-level encryption (LUKS)**

```bash
# Buat encrypted volume
sudo cryptsetup luksFormat /dev/sdb1
sudo cryptsetup luksOpen /dev/sdb1 dcm4chee_storage
sudo mkfs.ext4 /dev/mapper/dcm4chee_storage

# Mount ke storage directory
sudo mkdir -p /mnt/encrypted_storage
sudo mount /dev/mapper/dcm4chee_storage /mnt/encrypted_storage

# Symlink ke DCM4CHEE
sudo ln -sf /mnt/encrypted_storage /opt/dcm4chee-arc/storage
```

**Metode 2: S3 Storage dengan Server-Side Encryption**

```yaml
# Konfigurasi S3 storage via Web UI
# Configuration → Devices → DCM4CHEE → Storage → Create

Storage ID: S3_PROD
URI: s3://my-bucket/archive
Access Key: <AWS_ACCESS_KEY>
Secret Key: <AWS_SECRET_KEY>
SSE: AES256  # Server-side encryption
```

**Metode 3: NAS dengan hardware encryption**

Gunakan NAS yang support hardware encryption (Synology, QNAP dengan AES-NI).

---

## Modul 2: Backup & Disaster Recovery

### 2.1 Backup Strategy Overview

**3-2-1 Backup Rule:**
- **3** copies of data
- **2** different storage media
- **1** offsite / cloud location

**DCM4CHEE Backup Components:**

| Component | Data Type | Critical | Backup Frequency |
|-----------|-----------|----------|-----------------|
| PostgreSQL DB | Metadata | HIGH | Every 4h (incremental), daily (full) |
| DICOM Files | Images | HIGH | Continuous/realtime sync |
| LDAP Data | Configuration | HIGH | Daily + on config change |
| WildFly Config | Settings | MEDIUM | Before config change |
| Keycloak Data | Auth DB | HIGH | Daily |

### 2.2 PostgreSQL Backup

**Automated Full Backup Script:**

```bash
cat > /opt/dcm4chee-arc/backup_db.sh << 'SCRIPT'
#!/bin/bash
set -e

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/mnt/backup-nas/dcm4chee/db"
DB_CONTAINER="dcm4chee-db"
DB_NAME="pacsdb"
DB_USER="pacs"
RETENTION_DAYS=30

mkdir -p "$BACKUP_DIR"

# Full backup dengan compression
echo "[$(date)] Starting PostgreSQL full backup..."
docker exec "$DB_CONTAINER" pg_dump -U "$DB_USER" -d "$DB_NAME" \
  -Fc -f "/tmp/backup_${DATE}.dump"

# Copy dari container ke host
docker cp "$DB_CONTAINER:/tmp/backup_${DATE}.dump" \
  "$BACKUP_DIR/pacsdb_${DATE}.dump"

# Compression
gzip -9 "$BACKUP_DIR/pacsdb_${DATE}.dump"

# Verify backup
pg_restore --list "$BACKUP_DIR/pacsdb_${DATE}.dump.gz" | head -5

# Hapus backup lama
find "$BACKUP_DIR" -name "pacsdb_*.dump.gz" -mtime +$RETENTION_DAYS -delete

echo "[$(date)] Backup completed: pacsdb_${DATE}.dump.gz"
echo "Backup size: $(du -sh "$BACKUP_DIR/pacsdb_${DATE}.dump.gz" | cut -f1)"
SCRIPT

chmod +x /opt/dcm4chee-arc/backup_db.sh
```

**Incremental Backup (WAL Archive):**

```bash
# Enable WAL archiving
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET wal_level = replica;"
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET archive_mode = on;"
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET archive_command = 'test ! -f /mnt/backup-nas/dcm4chee/wal/%f && cp %p /mnt/backup-nas/dcm4chee/wal/%f';"
docker compose restart db
```

### 2.3 DICOM Storage Backup (rsync)

```bash
cat > /opt/dcm4chee-arc/backup_storage.sh << 'SCRIPT'
#!/bin/bash
set -e

DATE=$(date +%Y%m%d)
BACKUP_DEST="/mnt/backup-nas/dcm4chee/storage"
SOURCE_DIR="/opt/dcm4chee-arc/storage"
LOG_FILE="/var/log/dcm4chee-backup.log"

echo "[$(date)] Starting storage backup..." >> "$LOG_FILE"

# rsync dengan delete older files yang tidak ada di source
rsync -avh --delete \
  --exclude '*.tmp' \
  --exclude '*.incomplete' \
  "$SOURCE_DIR/" "$BACKUP_DEST/" >> "$LOG_FILE" 2>&1

# Verify backup
SOURCE_COUNT=$(find "$SOURCE_DIR" -type f | wc -l)
BACKUP_COUNT=$(find "$BACKUP_DEST" -type f | wc -l)

if [ "$SOURCE_COUNT" -eq "$BACKUP_COUNT" ]; then
  echo "[$(date)] Storage backup verified: $SOURCE_COUNT files" >> "$LOG_FILE"
else
  echo "[$(date)] WARNING: File count mismatch! Source=$SOURCE_COUNT, Backup=$BACKUP_COUNT" >> "$LOG_FILE"
fi
SCRIPT

chmod +x /opt/dcm4chee-arc/backup_storage.sh
```

### 2.4 LDAP & Configuration Backup

```bash
cat > /opt/dcm4chee-arc/backup_config.sh << 'SCRIPT'
#!/bin/bash
set -e

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/mnt/backup-nas/dcm4chee/config"
PROJECT_DIR="/opt/dcm4chee-arc"

mkdir -p "$BACKUP_DIR"

# Backup LDAP data (LDIF export)
docker exec dcm4chee-ldap ldapsearch -x -H ldap://localhost \
  -D "cn=admin,dc=dcm4che,dc=org" \
  -w "$LDAP_PASSWORD" \
  -b "dc=dcm4che,dc=org" \
  -LLL > "$BACKUP_DIR/ldap_config_${DATE}.ldif"

# Backup Docker volumes
for vol in ldap_data db_data wildfly_data keycloak_data; do
  tar -czf "$BACKUP_DIR/${vol}_${DATE}.tar.gz" \
    "$PROJECT_DIR/${vol#*_}" 2>/dev/null || true
done

# Backup docker-compose.yml
cp "$PROJECT_DIR/docker-compose.yml" "$BACKUP_DIR/docker-compose_${DATE}.yml"

# Backup .env (tanpa secrets nyata - buat template)
grep -v "PASSWORD\|SECRET\|KEY" "$PROJECT_DIR/.env" > \
  "$BACKUP_DIR/.env.template" 2>/dev/null || true

echo "[$(date)] Config backup completed" >> /var/log/dcm4chee-backup.log
SCRIPT

chmod +x /opt/dcm4chee-arc/backup_config.sh
```

### 2.5 Automated Backup Schedule

```bash
# Setup cron jobs
sudo crontab -l 2>/dev/null | grep -v dcm4chee > /tmp/current_cron
cat >> /tmp/current_cron << 'EOF'
# DCM4CHEE Backup Schedule
# PostgreSQL full backup - setiap 6 jam
0 */6 * * * /opt/dcm4chee-arc/backup_db.sh >> /var/log/dcm4chee-backup.log 2>&1

# DICOM storage sync - setiap jam
30 * * * * /opt/dcm4chee-arc/backup_storage.sh >> /var/log/dcm4chee-backup.log 2>&1

# LDAP & config backup - setiap hari jam 2 pagi
0 2 * * * /opt/dcm4chee-arc/backup_config.sh >> /var/log/dcm4chee-backup.log 2>&1

# Weekly verify backup integrity
0 3 * * 0 /opt/dcm4chee-arc/verify_backup.sh >> /var/log/dcm4chee-backup.log 2>&1
EOF
sudo crontab /tmp/current_cron
```

### 2.6 Disaster Recovery Procedure

**Recovery dari Backup:**

```bash
#!/bin/bash
# restore_dcm4chee.sh - jalankan saat disaster recovery

echo "=== DCM4CHEE Disaster Recovery ==="
read -p "Are you sure? This will overwrite current data. Type 'YES': " confirm
[ "$confirm" != "YES" ] && echo "Aborted." && exit 1

BACKUP_DATE="20250118"  # Set sesuai backup yang mau direstore

# 1. Stop semua service
cd /opt/dcm4chee-arc
docker compose down

# 2. Restore PostgreSQL
docker compose up -d db
sleep 30
gunzip -k /mnt/backup-nas/dcm4chee/db/pacsdb_${BACKUP_DATE}.dump.gz
docker exec dcm4chee-db dropdb -U pacs pacsdb
docker exec dcm4chee-db createdb -U pacs pacsdb
docker exec -i dcm4chee-db pg_restore -U pacs -d pacsdb \
  < /mnt/backup-nas/dcm4chee/db/pacsdb_${BACKUP_DATE}.dump

# 3. Restore LDAP
docker compose up -d ldap
sleep 30
docker exec dcm4chee-ldap slapd_configs_replaced || true
docker exec dcm4chee-ldap ldapdelete -x -D "cn=admin,dc=dcm4che,dc=org" \
  -w "$LDAP_PASSWORD" "dc=dcm4che,dc=org" 2>/dev/null || true
docker exec -i dcm4chee-ldap ldapadd -x -D "cn=admin,dc=dcm4che,dc=org" \
  -w "$LDAP_PASSWORD" < /mnt/backup-nas/dcm4chee/config/ldap_config_${BACKUP_DATE}.ldif

# 4. Restore storage
rsync -avh /mnt/backup-nas/dcm4chee/storage/ /opt/dcm4chee-arc/storage/
sudo chown -R 999:999 /opt/dcm4chee-arc/storage

# 5. Start semua service
docker compose up -d

echo "=== Recovery Complete ==="
echo "Verify at: http://localhost:8080/dcm4chee-arc/ui2"
```

**RTO (Recovery Time Objective):**
- RS Kecil: 4-8 jam (acceptable)
- RS Menengah: 1-4 jam (target)
- RS Besar/Rujukan: < 1 jam (butuh HA cluster)

**RPO (Recovery Point Objective):**
- RS Kecil: 24 jam (daily backup OK)
- RS Menengah: 4 jam (incremental backup)
- RS Besar/Rujukan: < 1 jam (continuous replication)

---

## Modul 3: High Availability & Clustering

### 3.1 HA Options untuk DCM4CHEE

| HA Strategy | Complexity | RTO | Cost | Use Case |
|-------------|-----------|-----|------|---------|
| Single instance + monitor | Low | 15-30 min | Low | RS kecil-menengah |
| Active-Passive failover | Medium | 5-15 min | Medium | RS menengah |
| Active-Active cluster | High | < 5 min | High | RS besar/rujukan |
| Kubernetes deployment | Very High | < 1 min | Very High | Enterprise |

### 3.2 Active-Passive Failover Setup

**Arsitektur:**

```
┌─────────────────────────────────────────────────────────────────┐
│              ACTIVE-PASSIVE FAILOVER                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────┐     ┌──────────┐                                  │
│  │Primary   │     │Secondary │                                  │
│  │Server    │     │Server    │                                  │
│  │(Active)  │     │(Passive) │                                  │
│  │          │     │          │                                  │
│  │ARC (Up)  │     │ARC(Stop) │                                  │
│  │LDAP(Up)  │     │LDAP(Stop)│                                  │
│  │DB(Up+Sync)│    │DB(Repl) │                                  │
│  │          │     │          │                                  │
│  └────┬─────┘     └────┬─────┘                                  │
│       │ DB Repl        │                                        │
│       └────┬───────────┘                                        │
│            │                                                     │
│  ┌─────────▼─────────┐                                          │
│  │  Storage (NAS)    │  ← Shared storage (RSYNC)                │
│  │  /opt/dcm4chee-arc│                                         │
│  └──────────────────┘                                          │
│                                                                 │
│  ┌─────────────────┐                                          │
│  │ Keepalived VIP   │  ← Floating IP: 192.168.1.100             │
│  │ 192.168.1.100    │                                          │
│  └────────┬─────────┘                                          │
│           │                                                     │
│  ┌────────▼──────────▼─────────────────────────┐             │
│  │           Modality / Client                   │             │
│  │  connect ke VIP (192.168.1.100)              │             │
│  └──────────────────────────────────────────────┘             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Setup pada Primary Server:**

```bash
# Install keepalived
sudo apt-get install -y keepalived

# Buat keepalived config
cat > /tmp/keepalived.conf << 'EOF'
vrrp_instance VI_DCM4CHEE {
    state BACKUP
    interface eth0
    virtual_router_id 51
    priority 100
    advert_int 1
    nopreempt

    virtual_ipaddress {
        192.168.1.100/24
    }

    track_script {
        check_dcm4chee
    }
}

track_script {
    check_dcm4chee {
        script "/opt/dcm4chee-arc/check_health.sh"
        interval 10
        weight 50
    }
}
EOF

sudo cp /tmp/keepalived.conf /etc/keepalived/
sudo chmod +x /opt/dcm4chee-arc/check_health.sh

# Health check script
cat > /opt/dcm4chee-arc/check_health.sh << 'SCRIPT'
#!/bin/bash
CONTAINER="dcm4chee-arc"
STATUS=$(docker inspect --format='{{.State.Health.Status}}' $CONTAINER 2>/dev/null)
if [ "$STATUS" = "healthy" ]; then
    exit 0
else
    exit 1
fi
SCRIPT

sudo systemctl enable keepalived
sudo systemctl start keepalived
```

**Setup pada Secondary Server:**

Lakukan hal yang sama, tapi ubah:
- `priority 90` (lebih rendah dari primary)
- Same VRRP configuration

**Sync storage menggunakan rsync:**

```bash
# Setup rsync daemon untuk storage sync
cat > /tmp/rsyncd.conf << 'EOF'
uid = 999
gid = 999
read only = false
use chroot = true
max connections = 5
lock file = /var/run/rsyncd.lock
log file = /var/log/rsyncd.log
timeout = 300

[dcm4chee_archive]
    path = /opt/dcm4chee-arc/storage
    comment = DCM4CHEE DICOM Archive
    exclude = .snapshot
EOF

# Enable rsync daemon (passive server sebagai rsyncd)
sudo apt-get install -y rsync xinetd
sudo cp /tmp/rsyncd.conf /etc/rsyncd.conf
sudo systemctl enable xinetd
sudo systemctl start xinetd
```

### 3.3 Load Balancer untuk Multiple Archive Instances

Untuk RS besar, gunakan **HAProxy** atau **Nginx** sebagai load balancer.

```bash
# HAProxy config untuk DCM4CHEE
cat > /etc/haproxy/haproxy.cfg << 'EOF'
global
    log /dev/log local0
    maxconn 4096
    user haproxy
    group haproxy

defaults
    log     global
    mode    tcp
    option  tcplog
    option  dontlognull
    timeout connect 5000ms
    timeout client  50000ms
    timeout server  50000ms

# DICOM port 11112
listen dicom
    bind 192.168.1.100:11112
    mode tcp
    balance roundrobin
    option tcp-check
    server arc1 192.168.1.101:11112 check inter 10s fall 2 rise 2
    server arc2 192.168.1.102:11112 check inter 10s fall 2 rise 2

# Web UI port 8080
listen webui
    bind 192.168.1.100:8080
    mode http
    balance roundrobin
    option httpchk GET /dcm4chee-arc/ui2/
    server arc1 192.168.1.101:8080 check inter 5s fall 2 rise 2
    server arc2 192.168.1.102:8080 check inter 5s fall 2 rise 2

# WildFly console
listen admin
    bind 192.168.1.100:9990
    mode http
    balance roundrobin
    server arc1 192.168.1.101:9990 check
    server arc2 192.168.1.102:9990 check
EOF

sudo systemctl enable haproxy
sudo systemctl start haproxy
```

### 3.4 Database Clustering (PostgreSQL Streaming Replication)

```bash
# Enable streaming replication di Primary

# postgresql.conf (Primary)
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET listen_addresses = '*';"
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET wal_level = replica;"
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET max_wal_senders = 3;"
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET wal_keep_size = 256;"

# pg_hba.conf - tambahkan replication user
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "ALTER SYSTEM SET host replication all 0.0.0.0/0 md5;"

docker compose restart db

# Buat replication user
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "CREATE USER replicator WITH REPLICATION ENCRYPTED PASSWORD 'repl_pass_2025';"

# Create replication slot
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT pg_create_physical_replication_slot('dcm4chee_slot');"
```

---

## Modul 4: Performance Tuning Lanjutan

### 4.1 JVM Performance Tuning

```yaml
# docker-compose.yml - ARC environment
services:
  arc:
    environment:
      WILDFLY_JAVA_OPTS: >-
        -Xms4g
        -Xmx8g
        -XX:+UseG1GC
        -XX:MaxGCPauseMillis=200
        -XX:+UseStringDeduplication
        -Djava.security.egd=file:/dev/./urandom
        -Djboss.socket.binding.port-offset=0
```

**G1GC Tuning:**
- `Xms` = initial heap (set = Xmx untuk evitar resize)
- `Xmx` = max heap (jangan lebih dari 50% RAM system)
- `MaxGCPauseMillis=200` → target GC pause < 200ms

### 4.2 PostgreSQL Performance Monitoring

```sql
-- Enable extensions
ALTER DATABASE pacsdb SET shared_preload_libraries = 'pg_stat_statements';
ALTER SYSTEM SET pg_stat_statements.track = 'all';

-- Cek slow queries
SELECT query,
       calls,
       total_exec_time,
       mean_exec_time,
       rows,
       100.0 * shared_blks_hit / NULLIF(shared_blks_hit + shared_blks_read, 0) AS cache_hit_ratio
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 20;

-- Cek index usage
SELECT indexrelname,
       idx_scan,
       idx_tup_read,
       idx_tup_fetch,
       pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE idx_scan < 100 AND idx_tup_read > 0
ORDER BY idx_scan ASC;

-- Cek table bloat
SELECT tablename,
       pg_size_pretty(pg_total_relation_size(tablename::regclass)) AS total_size,
       pg_size_pretty(pg_relation_size(tablename::regclass)) AS table_size,
       n_dead_tup,
       n_live_tup,
       last_vacuum,
       last_autovacuum
FROM pg_stat_user_tables
ORDER BY pg_total_relation_size(tablename::regclass) DESC
LIMIT 10;

-- VACUUM untuk reclaim space
VACUUM ANALYZE patients;
VACUUM ANALYZE studies;
VACUUM ANALYZE series;
VACUUM ANALYZE instances;
```

### 4.3 DICOM Storage Performance

```bash
# Cek I/O performance
sudo apt-get install -y fio

# Test sequential write
fio --name=seqwrite --rw=write --bs=1m --size=1G --directory=/opt/dcm4chee-arc/storage

# Test random read (simulate DICOM retrieve)
fio --name=randread --rw=randread --bs=4k --size=1G --directory=/opt/dcm4chee-arc/storage

# Recommended performance:
# Sequential Write: > 200 MB/s
# Random Read: > 500 IOPS (4K blocks)
```

**Storage tiering strategy:**

```yaml
# docker-compose.yml dengan multi-tier storage
services:
  arc:
    environment:
      # Tier 1: Active studies (< 30 days)
      ARCHIVE_STORAGE_DIR: /storage/active
      ARCHIVE_NEARLINE_STORAGE_DIR: /storage/archive
    volumes:
      - /fast-ssd/storage:/storage/active
      - /nas-hdd/storage:/storage/archive
```

### 4.4 Network Performance

```bash
# Cek network bandwidth antar servers
iperf3 -s -p 5201  # di server
iperf3 -c server_ip -p 5201 -t 30  # di client

# Target: > 100 MB/s untuk DICOM traffic
```

**Docker network optimization:**

```yaml
services:
  arc:
    networks:
      dcm4chee_network:
        driver: bridge
        enable_ipv6: false
    # Matikan DNS search untuk speed
    dns_search: ""
```

---

## Modul 5: Advanced Monitoring dengan Elastic Stack

### 5.1 Architecture Elastic Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                 ELASTIC STACK MONITORING                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐   │
│  │   Logstash   │────▶│Elasticsearch │◀────│   Kibana     │   │
│  │  Port 8514   │     │  Port 9200   │     │  Port 5601   │   │
│  │(GELF input)  │     │              │     │              │   │
│  └──────┬───────┘     └──────┬───────┘     └──────────────┘   │
│         │                    │                                  │
│  ┌──────▼───────┐     ┌──────▼───────┐                        │
│  │  DCM4CHEE    │     │   Kibana     │                        │
│  │  (GELF logs) │     │ (OAuth2)     │                        │
│  │              │     │              │                        │
│  │  + Audit     │     │  + Audit     │                        │
│  │  + Access    │     │    Logs      │                        │
│  │  + System    │     │  + Metrics   │                        │
│  └──────────────┘     └──────────────┘                        │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Data Streams                            │  │
│  │  dcm4chee-archive-*/ (application logs)                  │  │
│  │  dcm4chee-audit-*/ (audit records)                       │  │
│  │  dcm4chee-metrics-*/ (JVM, DB metrics)                   │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Full Stack docker-compose.yml

```yaml
version: "3.8"

services:
  # ... existing ldap, db, arc, keycloak, oauth2-proxy ...

  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.15.1
    container_name: dcm4chee-elasticsearch
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms2g -Xmx2g"
      - cluster.name=dcm4chee-monitor
    ports:
      - "9200:9200"
      - "9300:9300"
    volumes:
      - es_data:/usr/share/elasticsearch/data
    healthcheck:
      test: ["CMD-SHELL", "curl -s http://localhost:9200/_cluster/health | grep -q '\\"status\\":\\"green\\"\\|\\"status\\":\\"yellow\\"'"]
      interval: 30s
      timeout: 10s
      retries: 5
    restart: unless-stopped

  logstash:
    image: dcm4che/logstash-dcm4chee:8.15.1-18
    container_name: dcm4chee-logstash
    environment:
      - "LS_JAVA_OPTS=-Xms512m -Xmx512m"
      - LOGSTASH_HOST=logstash
    ports:
      - "12201:12201/udp"
      - "8514:8514/udp"
    volumes:
      - ./logstash/filter-hashtree:/usr/share/logstash/data/filter-hashtree
    depends_on:
      elasticsearch:
        condition: service_healthy
    restart: unless-stopped

  kibana:
    image: docker.elastic.co/kibana/kibana:8.15.1
    container_name: dcm4chee-kibana
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
      - SERVER_NAME=dcm4chee-kibana
    ports:
      - "5601:5601"
    depends_on:
      elasticsearch:
        condition: service_healthy
    restart: unless-stopped

volumes:
  es_data:
```

### 5.3 Kibana Dashboard Setup

**Akses Kibana:**
```
URL: http://server:5601
Auth: via OAuth2 Proxy (http://server:8843/kibana)
```

**Buat index patterns:**

1. Buka **Stack Management → Index Patterns → Create index pattern**
2. Buat pattern untuk:

| Index Pattern | Time Field |
|---------------|-----------|
| `dcm4chee-archive-*` | `@timestamp` |
| `dcm4chee-audit-*` | `@timestamp` |

**Sample Dashboard Visualizations:**

- **Storage Usage Over Time**: Line chart dari `dcm4chee-archive-*`
- **DICOM Operations per Hour**: Bar chart dari `dcm4chee-archive-*`
- **Error Rate**: Metric dengan filter `level:ERROR`
- **Study Volume by Modality**: Pie chart dari audit records
- **Top Slow Queries**: Table dari `dcm4chee-archive-*` dengan filter `query_time:>1000`

---

## Modul 6: Compliance & Audit

### 6.1 IHE ATNA Compliance

**ATNA (Audit Trail and Node Authentication)** adalah standar IHE untuk:
1. **Audit Trail** - Logging semua security-relevant events
2. **Node Authentication** - Secure node-to-node communication

DCM4CHEE menyediakan syslog-based audit logging yang compliant:

```bash
# Configure audit logger via Web UI
# Configuration → Devices → DCM4CHEE → Audit Record Repository

# Settings:
| Setting | Value |
|---------|-------|
| `Audit Source ID` | DCM4CHEE_ARCHIVE |
| `Audit Logger Type` | SYSLOG (TLS) |
| `Syslog Server` | elasticsearch:5514 |
| `TLS` | true |
| `Include Patient Data` | sesuai kebutuhan |
```

**Events yang harus di-log:**

| Event | Category | Retention |
|-------|----------|-----------|
| User login/logout | Authentication | 7 years |
| Patient record access | Privacy | 7 years |
| Study create/read/update/delete | Clinical | 7 years |
| Export/Print | Export | 7 years |
| Configuration change | Administrative | 7 years |
| Failed login attempts | Security | 7 years |

### 6.2 Data Retention Policy

```bash
# Configure retention via Web UI
# Configuration → Devices → DCM4CHEE → Studies → Retention Policy

# Contoh: 7 tahun untuk adult studies
| Rule | Value |
|------|-------|
| `Type` | EXPIRY |
| `Trigger` | Time-based |
| `Days Since Study Date` | 2555 (7 years) |
| `Action` | Mark for deletion / Move to archive tier |
```

**Automated deletion:**

```bash
# Expire studies script (jalankan via cron)
#!/bin/bash
RETENTION_DAYS=2555
CUTOFF_DATE=$(date -d "$RETENTION_DAYS days ago" +%Y-%m-%d)

# Mark expired studies
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "UPDATE studies SET exp_time = '$CUTOFF_DATE'::timestamp \
   WHERE study_date < '$CUTOFF_DATE'::timestamp \
   AND exp_time IS NULL;"

# Verify
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT count(*) FROM studies \
   WHERE exp_time IS NOT NULL AND exp_time < now();"
```

---

## Modul 7: Migration dari PACS Lama

### 7.1 Pre-Migration Assessment

**Inventory sistem lama:**

```bash
# Scan untuk DICOM files di storage lama
du -sh /old/pacs/storage/
find /old/pacs/storage/ -type f -name "*.dcm" | wc -l

# Estimasi:
# - Total storage usage
# - Number of studies
# - Age range (date range)
# - Modalities represented

# Export patient/study metadata
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "COPY (SELECT pat_id, pat_name, pat_birth_date, count(*) as study_count \
   FROM patients p JOIN studies s ON p.pat_id = s.pat_id \
   GROUP BY pat_id, pat_name, pat_birth_date) \
   TO '/tmp/patient_inventory.csv' WITH CSV HEADER;"
```

### 7.2 Migration Methods

**Method 1: Storage Import (Recommended)**

Ini import file DICOM langsung ke DCM4CHEE tanpa perlu modality mengirim ulang:

```bash
# 1. Konfigurasi old storage sebagai "Generic Storage"
# Configuration → Devices → DCM4CHEE → Storage → Create

# Contoh:
| Field | Value |
|-------|-------|
| Storage ID | OLD_PACS |
| URI | /mnt/old_pacs/storage |
| Type | Generic Storage |
| Read Only | true |

# 2. Bulk import via REST API
STORAGE_ID="OLD_PACS"
STORAGE_PATH="/mnt/old_pacs/storage"

# Import semua file dari tahun 2020
find "$STORAGE_PATH/2020" -type f | \
  curl -v -X POST \
    -H "Content-Type: text/plain" \
    --data-binary @- \
    "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/instances/storage/$STORAGE_ID"

# 3. Coerce attributes jika perlu (update Patient Name dll)
find "$STORAGE_PATH/2020/05/13/Study123" -type f | \
  curl -v -X POST \
    -H "Content-Type: text/plain" \
    --data-binary @- \
    "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/instances/storage/$STORAGE_ID?PatientName=CorrectName%5ECorrectID&updatePolicy=SUPPLEMENT"
```

**Method 2: Re-send via DICOM C-STORE**

Jika sistem lama masih bisa connect:

```bash
# Gunakan dcm4che storescu untuk re-send semua study
# Ambil list study UIDs
STUDY_UIDS=$(docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT study_iuid FROM studies WHERE study_date >= '2015-01-01' AND study_date <= '2020-12-31';" -t)

for UID in $STUDY_UIDS; do
  # Retrieve dari sistem lama
  find /old/pacs/storage/ -name "*$UID*" -exec \
    ./dcm4che/bin/storescu old-pacs-host 11112 {} \;
done
```

### 7.3 Post-Migration Verification

```bash
# 1. Count verification
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT 'Original PACS' as source, count(*) as studies FROM old_pacs.studies
   UNION ALL
   SELECT 'DCM4CHEE', count(*) FROM studies;"

# 2. Random sample check
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT s.study_iuid, p.pat_name, s.study_date, s.modality, s.num_instances \
   FROM studies s JOIN patients p ON s.pat_id = p.pat_id \
   ORDER BY random() LIMIT 10;"

# 3. Checksum verification
docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT s.study_iuid, i.storage_path, i.dcm_md5 \
   FROM instances i JOIN series se ON i.series_iuid = se.series_iuid \
                     JOIN studies s ON se.study_iuid = s.study_iuid \
   WHERE i.dcm_md5 IS NOT NULL LIMIT 10;"

# 4. Accessibility check - retrieve via QIDO
curl "http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs/studies?limit=10" | jq .
```

---

## Modul 8: Studi Kasus — RS Rujukan (10+ Modalities, 200+ Beds)

### 8.1 Profile

- **Modalitas:** 10+ unit (2×CT, 2×MRI, 3×CR, 2×USG, 1×XA, 1×Mammo)
- **Study/hari:** 300-500
- **Bed:** 200-500
- **User:** 20-50 radiologist/technologist
- **Integrasi:** HIS full (SIMRS), RIS, EMR, LIS, PACS viewer
- **SLA:** 99.9% uptime, RTO < 1 jam, RPO < 1 jam

### 8.2 Arsitektur RS Rujukan

```
┌────────────────────────────────────────────────────────────────────────┐
│                     RS RUJUKAN (200+ BEDS)                              │
├────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│                         INTERNET / WAN                                   │
│                              │                                           │
│                    ┌─────────▼─────────┐                                │
│                    │   DMZ / Proxy    │                                │
│                    │   Load Balancer  │                                │
│                    │   (HAProxy)     │                                │
│                    └─────────┬─────────┘                                │
│                              │                                           │
│  ┌──────────────────────────┼───────────────────────────┐               │
│  │                          │                           │               │
│  │    ┌─────────────────────▼──────────────┐          │               │
│  │    │    High Availability Cluster        │          │               │
│  │    │                                      │          │               │
│  │    │  ┌─────────┐  ┌─────────┐  ┌─────────┐ │          │               │
│  │    │  │  ARC1   │  │  ARC2   │  │  ARC3   │ │          │               │
│  │    │  │(Active) │  │(Active) │  │(Standby)│ │          │               │
│  │    │  │ HAProxy │  │ HAProxy │  │         │ │          │               │
│  │    │  └────┬────┘  └────┬────┘  └─────────┘ │          │               │
│  │    │       │ DB Repl    │                        │               │
│  │    └───────┼────────────┼──────────────────────┘               │
│  │            │            │                                        │
│  │  ┌─────────▼────────────▼─────────┐                            │
│  │  │   PostgreSQL Primary + Replica  │                            │
│  │  │   Streaming Replication        │                            │
│  │  └────────────────────────────────┘                            │
│  │                                                                    │
│  │  ┌────────────────────────────────────┐                        │
│  │  │   NAS Storage (Tiered)             │                        │
│  │  │  Tier1: SSD (active < 90 days)     │                        │
│  │  │  Tier2: NAS HDD (90-365 days)      │                        │
│  │  │  Tier3: Object Storage (> 365 days)│                        │
│  │  └────────────────────────────────────┘                        │
│  │                                                                    │
│  │  ┌────────────────────────────────────────────────────┐          │
│  │  │               Modality Network (VLAN isolated)    │          │
│  │  │   CT1 │ CT2 │ MRI1 │ MRI2 │ CR1 │ CR2 │ CR3 │ ... │          │
│  │  └────────────────────────────────────────────────────┘          │
│  │                                                                    │
│  │  ┌────────────────────────────────────────────────────┐          │
│  │  │          EMR/RIS Integration (HL7 FHIR)           │          │
│  │  │   HIS → HL7 ORM → DCM4CHEE MWL                    │          │
│  │  │   EMR → FHIR → DCM4CHEE REST                       │          │
│  │  └────────────────────────────────────────────────────┘          │
│  │                                                                    │
│  │  ┌────────────────────────────────────────────────────┐          │
│  │  │               Monitoring & Alerting                 │          │
│  │  │   Elastic Stack + Grafana + Prometheus             │          │
│  │  │   Alert: Slack, Email, SMS                         │          │
│  │  └────────────────────────────────────────────────────┘          │
│  │                                                                    │
│  │  ┌────────────────────────────────────────────────────┐          │
│  │  │            Security: TLS + Keycloak SSO            │          │
│  │  │   Keycloak Cluster + OAuth2 Proxy                   │          │
│  │  │   DICOM TLS untuk modality sensitive              │          │
│  │  └────────────────────────────────────────────────────┘          │
│  │                                                                    │
│  │  ┌────────────────────────────────────────────────────┐          │
│  │  │            Backup & Disaster Recovery              │          │
│  │  │   Primary Backup: Local NAS (hourly)               │          │
│  │  │   Offsite Backup: Cloud S3 (daily)                 │          │
│  │  │   DR Site: Secondary DC (async replication)        │          │
│  │  └────────────────────────────────────────────────────┘          │
│  │                                                                    │
└────────────────────────────────────────────────────────────────────────┘
```

### 8.3 Konfigurasi untuk 500 Study/Hari

**Database sizing:**

| Metric | Value |
|--------|-------|
| Study/hari | 500 |
| Avg instances/study | 150 |
| Total instances/hari | 75,000 |
| Avg metadata size | 3 KB |
| DB growth/hari | ~225 MB metadata |
| DB growth/bulan | ~6.7 GB |
| DB size untuk 3 tahun | ~240 GB |

**PostgreSQL config:**

```bash
# postgresql.conf untuk RS besar
cat >> /opt/dcm4chee-arc/db/postgresql.conf << 'EOF'
# Connection settings
max_connections = 200

# Memory (8GB available)
shared_buffers = 2GB
effective_cache_size = 6GB
work_mem = 64MB
maintenance_work_mem = 512MB

# WAL settings
wal_buffers = 64MB
max_wal_size = 4GB
min_wal_size = 1GB

# Query optimization
from_collapse_limit = 16
join_collapse_limit = 16

# Background writer
bgwriter_delay = 200ms
bgwriter_lru_maxpages = 1000
bgwriter_lru_multiplier = 5.0

# Checkpoint
checkpoint_timeout = 15min
checkpoint_completion_target = 0.9

# Parallel queries
max_worker_processes = 8
max_parallel_workers_per_gather = 4
EOF
```

**WildFly heap:**

```yaml
services:
  arc:
    environment:
      WILDFLY_JAVA_OPTS: >-
        -Xms8g -Xmx16g
        -XX:+UseG1GC
        -XX:MaxGCPauseMillis=100
        -XX:+ParallelRefProcEnabled
        -Djava.security.egd=file:/dev/./urandom
```

### 8.4 Monitoring & Alerting untuk RS Besar

```bash
cat > /opt/dcm4chee-arc/monitor_pro.sh << 'MON'
#!/bin/bash
# Production monitoring script

ALERT_EMAIL="oncall@rumahsakit.com"
SLACK_WEBHOOK="https://hooks.slack.com/services/XXX/YYY/ZZZ"
DCM_DIR="/opt/dcm4chee-arc"

send_alert() {
  local level=$1
  local message=$2
  echo "[$(date)] ALERT ($level): $message"

  # Email
  echo "$message" | mail -s "[DCM4CHEE $level] $(hostname)" $ALERT_EMAIL

  # Slack
  curl -s -X POST "$SLACK_WEBHOOK" \
    -H 'Content-Type: application/json' \
    -d "{\"text\":\"[DCM4CHEE $level] $message\"}"
}

# 1. Check all containers
for svc in ldap db arc keycloak elasticsearch logstash kibana; do
  container="dcm4chee-$svc"
  if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
    status=$(docker inspect --format='{{.State.Health.Status}}' $container 2>/dev/null)
    if [ "$status" != "healthy" ]; then
      send_alert "CRITICAL" "$container is $status"
    fi
  else
    send_alert "CRITICAL" "$container is not running"
  fi
done

# 2. Check storage usage
USAGE=$(df -h $DCM_DIR/storage | tail -1 | awk '{print $5}' | tr -d '%')
if [ "$USAGE" -gt 70 ]; then
  send_alert "WARNING" "Storage usage at ${USAGE}%"
fi
if [ "$USAGE" -gt 90 ]; then
  send_alert "CRITICAL" "Storage usage at ${USAGE}% - CRITICAL!"
fi

# 3. Check database size
DB_SIZE=$(docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT pg_size_pretty(pg_database_size('pacsdb'));" -t | tr -d ' ')
DB_SIZE_GB=$(docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT pg_database_size('pacsdb')/1024/1024/1024;" -t | tr -d ' ')

if [ "$DB_SIZE_GB" -gt 200 ]; then
  send_alert "WARNING" "Database size: $DB_SIZE"
fi

# 4. Check error rate
ERROR_COUNT=$(docker logs dcm4chee-arc --since 1h 2>&1 | grep -ci "ERROR\|SEVERE\|Exception" || echo 0)
if [ "$ERROR_COUNT" -gt 20 ]; then
  send_alert "WARNING" "$ERROR_COUNT errors in last hour"
fi

# 5. Check JVM heap
HEAP_USED=$(docker exec dcm4chee-arc /opt/wildfly/bin/jboss-cli.sh \
  -c "/core-service=platform-mbean/type=memory:read-memory-usage" 2>/dev/null | \
  grep "heap-memory-usage.used" | grep -o '[0-9]*' | head -1)
HEAP_COMMITTED=$(docker exec dcm4chee-arc /opt/wildfly/bin/jboss-cli.sh \
  -c "/core-service=platform-mbean/type=memory:read-memory-usage" 2>/dev/null | \
  grep "heap-memory-usage.committed" | grep -o '[0-9]*' | head -1)

HEAP_PCT=$((HEAP_USED * 100 / HEAP_COMMITTED))
if [ "$HEAP_PCT" -gt 85 ]; then
  send_alert "WARNING" "JVM Heap usage at ${HEAP_PCT}%"
fi

# 6. Check study throughput (last hour)
STUDIES_1H=$(docker exec dcm4chee-db psql -U pacs -d pacsdb -c \
  "SELECT count(*) FROM studies WHERE study_date >= now() - interval '1 hour';" -t | tr -d ' ')
echo "$(date): $STUDIES_1H studies in last hour"
MON

chmod +x /opt/dcm4chee-arc/monitor_pro.sh
```

---

## Modul 9: Troubleshooting Advanced

### 9.1 Keycloak Issues

**Keycloak tidak bisa start:**

```bash
# Cek logs
docker logs dcm4chee-keycloak --tail 100

# Common fix: database connectivity
docker exec dcm4chee-keycloak /opt/keycloak/bin/kc.sh \
  show-config 2>/dev/null | grep "kc.db"

# Reset Keycloak jika corrupted
docker stop dcm4chee-keycloak
rm -rf ./keycloak/data/*
docker start dcm4chee-keycloak
```

**User tidak bisa login:**

```bash
# Reset user password via Keycloak CLI
docker exec dcm4chee-keycloak /opt/keycloak/bin/kc.sh \
  set-password -r dcm4che -u username --new-password "NewPassword123"
```

### 9.2 Database Performance Degradation

```sql
-- Identify slow queries
SELECT query,
       calls,
       total_exec_time / calls as avg_ms,
       rows / calls as avg_rows,
       (100.0 * shared_blks_hit / NULLIF(shared_blks_hit + shared_blks_read, 0))::int AS cache_hit
FROM pg_stat_statements
WHERE calls > 100
ORDER BY avg_ms DESC
LIMIT 10;

-- Analyze slow query plans
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT /* your slow query here */;
```

### 9.3 Network/Firewall Issues

```bash
# Test DICOM connectivity dari modality network
nc -zv modality-ip 11112
timeout 5 bash -c 'cat < /dev/null > /dev/tcp/modality-ip/11112' && echo "Port open"

# Test LDAP dari dalam container
docker exec dcm4chee-arc \
  ldapsearch -x -H ldap://ldap:389 \
  -b "dc=dcm4che,dc=org" -LLL "(objectClass=*)" dn

# Test PostgreSQL dari dalam container
docker exec dcm4chee-arc \
  PGPASSWORD=$DB_PASSWORD psql -h db -U pacs -d pacsdb -c "SELECT 1;"
```

---

## Ringkasan Level Advanced

```
Level Advanced Coverage:
├── Security: TLS/SSL + Keycloak SSO                  ✅
├── DICOM TLS Configuration                           ✅
├── Encryption at Rest (LUKS, S3 SSE)                 ✅
├── Backup Strategy (3-2-1 rule)                     ✅
├── PostgreSQL + WAL Backup                          ✅
├── Disaster Recovery Procedure                       ✅
├── High Availability (Active-Passive/Load Balancer)  ✅
├── PostgreSQL Streaming Replication                 ✅
├── Performance Tuning (JVM, DB, I/O)                ✅
├── Elastic Stack Monitoring (ELK)                   ✅
├── Kibana Dashboard Setup                            ✅
├── IHE ATNA Compliance & Audit Trail                ✅
├── Data Retention Policy                            ✅
├── PACS Migration Strategy                          ✅
├── Studi kasus RS Rujukan                           ✅
└── Advanced Troubleshooting                         ✅
```

---

## Course Completion Summary

```
╔══════════════════════════════════════════════════════════════════════╗
║               DCM4CHEE IMPLEMENTATION TRAINING                      ║
║                    COMPLETE COURSE SUMMARY                         ║
╠══════════════════════════════════════════════════════════════════════╣
║                                                                      ║
║  LEVEL 1 - BASIC                                                    ║
║  ├── Konsep DICOM (model data, services, SOP classes)    ✅         ║
║  ├── Arsitektur DCM4CHEE (WildFly, LDAP, PostgreSQL)      ✅         ║
║  ├── Instalasi Docker Compose                              ✅         ║
║  ├── Hands-on Exercises (4)                                 ✅         ║
║  ├── Arsitektur jaringan & DICOM flow                      ✅         ║
║  ├── Studi kasus RS Kecil                                   ✅         ║
║  └── Troubleshooting & best practices                       ✅         ║
║                                                                      ║
║  LEVEL 2 - INTERMEDIATE                                              ║
║  ├── Konfigurasi PACS (parameter sets, storage)           ✅         ║
║  ├── Modality Worklist (MWL) integration                  ✅         ║
║  ├── HIS/EMR integration (HL7 ORM/ADT)                   ✅         ║
║  ├── QR SCP Configuration (C-MOVE/C-GET)                 ✅         ║
║  ├── QIDO-RS REST API                                      ✅         ║
║  ├── PostgreSQL Optimization (production-ready)           ✅         ║
║  ├── Hands-on Exercises (4)                                 ✅         ║
║  ├── Studi kasus RS Menengah                                ✅         ║
║  ├── Monitoring & Alerting (basic)                        ✅         ║
║  └── HIS Integration troubleshooting                      ✅         ║
║                                                                      ║
║  LEVEL 3 - ADVANCED                                                  ║
║  ├── Security: TLS + Keycloak SSO                          ✅         ║
║  ├── DICOM TLS + Encryption at Rest                        ✅         ║
║  ├── Backup Strategy (3-2-1 rule)                          ✅         ║
║  ├── Disaster Recovery                                     ✅         ║
║  ├── High Availability (Active-Passive/Cluster)           ✅         ║
║  ├── PostgreSQL Streaming Replication                      ✅         ║
║  ├── Performance Tuning (JVM, DB, I/O, Network)          ✅         ║
║  ├── Elastic Stack Monitoring (ELK)                        ✅         ║
║  ├── Kibana Dashboard Setup                                ✅         ║
║  ├── IHE ATNA Compliance & Audit Trail                     ✅         ║
║  ├── Data Retention Policy                                 ✅         ║
║  ├── PACS Migration Strategy                               ✅         ║
║  ├── Studi kasus RS Rujukan (HA, DR, Monitoring)           ✅         ║
║  └── Advanced Troubleshooting                              ✅         ║
║                                                                      ║
║  TOTAL: 12 Modul + 8 Hands-on Exercises + 3 Studi Kasus             ║
║  SKILL FILE: dcm4chee-training/SKILL.md (DCM4CHEE Docker)            ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
```