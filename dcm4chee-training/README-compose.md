# DCM4CHEE Docker Compose Files
## Level-Specific Configurations

---

## Quick Start by Level

### Level 1: Basic (RS Kecil)
```bash
cd dcm4chee-training

# Copy dan edit environment
cp .env.template .env
nano .env

# Start minimum setup (LDAP + PostgreSQL + Archive)
docker compose -f docker-compose.level1-basic.yml up -d

# Verifikasi
docker compose -f docker-compose.level1-basic.yml ps
curl -s http://localhost:8080/dcm4chee-arc/ui2/ | head -5

# Access
# Web UI:     http://localhost:8080/dcm4chee-arc/ui2
# User:       root
# Password:   changeit
# DICOM Port: 11112
```

### Level 2: Intermediate (RS Menengah)
```bash
# Start with Level 1 + additional services (Monitoring, OHIF, Backup)
docker compose -f docker-compose.level2-intermediate.yml up -d

# Access tambahan:
# Grafana:   http://localhost:3000
# OHIF:      http://localhost:3001/viewer
```

### Level 3: Advanced (RS Rujukan)
```bash
# Generate SSL certificates dulu
cd certs
openssl req -x509 -nodes -newkey rsa:2048 \
  -keyout key.pem -out cert.pem \
  -subj "/CN=dcm4chee.local/O=DCM4CHEE/C=ID"
cd ..

# Start full production stack
docker compose -f docker-compose.level3-advanced.yml up -d

# Access:
# Archive UI:  https://localhost:8443/dcm4chee-arc/ui2 (via OAuth2)
# Keycloak:    https://localhost:8843/admin
# Kibana:      https://localhost:8843/kibana
# Grafana:     http://localhost:8300
# Elasticsearch: http://localhost:9200
```

---

## Service Comparison

| Service | Level 1 | Level 2 | Level 3 |
|---------|---------|---------|---------|
| **OpenLDAP** | ✅ | ✅ | ✅ |
| **PostgreSQL** | ✅ | ✅ | ✅ (tuned) |
| **DCM4CHEE Archive** | ✅ | ✅ | ✅ (secure) |
| **Grafana** | - | ✅ | ✅ |
| **OHIF Viewer** | - | ✅ | - |
| **Backup Scheduler** | - | ✅ | ✅ (enhanced) |
| **Keycloak** | - | - | ✅ |
| **OAuth2 Proxy** | - | - | ✅ |
| **Elasticsearch** | - | - | ✅ |
| **Logstash** | - | - | ✅ |
| **Kibana** | - | - | ✅ |
| **Resource Limits** | - | - | ✅ |
| **TLS/SSL** | - | - | ✅ |

---

## Environment Variables

### Required (all levels)
```bash
LDAP_PASSWORD          # OpenLDAP admin password
POSTGRES_PASSWORD      # PostgreSQL password
```

### Level 3 Additional
```bash
KEYCLOAK_PASSWORD      # Keycloak admin
OAUTH2_CLIENT_SECRET  # OAuth2 client secret
GRAFANA_PASSWORD       # Grafana admin
BACKUP_RETENTION_DAYS  # Backup retention (default: 30)
```

### Performance Tuning (Level 3)
```bash
# JVM Heap - sesuaikan dengan RAM
WILDFLY_JAVA_OPTS="-Xms4g -Xmx8g -XX:+UseG1GC"

# PostgreSQL memory (8GB available)
POSTGRES_SHARED_BUFFERS=2GB
POSTGRES_EFFECTIVE_CACHE_SIZE=6GB
```

---

## Directory Structure

```
dcm4chee-training/
├── docker-compose.level1-basic.yml       # RS Kecil
├── docker-compose.level2-intermediate.yml # RS Menengah
├── docker-compose.level3-advanced.yml     # RS Rujukan
├── .env.template                         # Environment template
├── README-compose.md                    # This file
│
├── backup/
│   └── scripts/
│       └── entrypoint.sh                # Backup automation
│
├── grafana/
│   ├── provisioning/
│   │   ├── dashboard.yml
│   │   ├── dashboards.yml
│   │   └── datasources/
│   │       └── dcm4chee.yml
│   └── dashboards/
│       └── dcm4chee-overview.json
│
├── ohif-config/
│   └── dicomweb.json                    # OHIF datasource config
│
├── logstash/
│   └── pipeline/
│       └── dcm4chee.conf               # Log processing config
│
├── db-config/
│   └── postgresql.conf                # Production PostgreSQL config
│
├── keycloak/
│   └── themes/                        # Custom Keycloak themes
│
├── kibana/
│   └── config/                        # Kibana configuration
│
└── certs/
    ├── cert.pem                        # SSL certificate
    └── key.pem                        # SSL private key
```

---

## Monitoring Endpoints

### Level 2
| Service | URL | Default Login |
|---------|-----|---------------|
| Archive Web UI | http://localhost:8080/dcm4chee-arc/ui2 | root/changeit |
| Grafana | http://localhost:3000 | admin/admin123 |

### Level 3
| Service | URL | Login |
|---------|-----|-------|
| Archive UI (Secured) | https://localhost:8443/dcm4chee-arc/ui2 | Keycloak SSO |
| Keycloak Admin | https://localhost:8843/admin | root/[KEYCLOAK_PASSWORD] |
| Kibana (via Proxy) | https://localhost:8843/kibana | Keycloak SSO |
| Grafana | http://localhost:8300 | admin/[GRAFANA_PASSWORD] |
| Elasticsearch | http://localhost:9200 | - |
| WildFly Console | http://localhost:9990 | root/changeit |

---

## Troubleshooting

### Container won't start
```bash
# Check logs
docker compose -f docker-compose.level1-basic.yml logs ldap
docker compose -f docker-compose.level1-basic.yml logs db
docker compose -f docker-compose.level1-basic.yml logs arc

# Common cause: LDAP/DB belum healthy
# Solution: tunggu 60 detik, atau cek health check
docker inspect dcm4chee-ldap --format '{{.State.Health}}'
docker inspect dcm4chee-db --format '{{.State.Health}}'
```

### Port already in use
```bash
# Check port usage
sudo ss -tlnp | grep -E '8080|11112|5432'

# Kill process jika perlu
sudo lsof -ti:8080 | xargs sudo kill -9
```

### Storage permission error
```bash
# Fix ownership
sudo chown -R 999:999 ./storage_data

# Check current ownership
ls -la . | grep storage
```

### Level 3: Keycloak won't start
```bash
# Check Keycloak logs
docker compose logs keycloak --tail 50

# Reset Keycloak data jika corrupted
docker compose stop keycloak
rm -rf ./keycloak_data
docker compose up -d keycloak
# Tunggu ~3 menit
```

### Backup script fails
```bash
# Check backup container logs
docker compose logs backup-scheduler

# Manual test backup
docker compose exec backup-scheduler /bin/bash
# Di dalam container:
/scripts/entrypoint.sh
```

---

## Performance Recommendations

| Level | RAM | CPU | Storage |
|-------|-----|-----|---------|
| **Level 1** | 4 GB | 2 cores | 100 GB |
| **Level 2** | 8 GB | 4 cores | 500 GB |
| **Level 3** | 32 GB | 8 cores | 2 TB+ |

---

## Security Checklist (Level 3)

- [ ] Ubah semua default passwords di .env
- [ ] Generate SSL certificates baru (jangan self-signed untuk production)
- [ ] Konfigurasi firewall (hanya buka port yang diperlukan)
- [ ] Setup Keycloak users dan roles
- [ ] Enable TLS untuk DICOM communications
- [ ] Setup automated backup ke offsite storage
- [ ] Test disaster recovery procedure