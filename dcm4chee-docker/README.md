# DCM4CHEE Archive 5.x - Docker Deployment

Pre-configured Docker Compose setup untuk DCM4CHEE Archive 5.x dengan PostgreSQL dan OpenLDAP.

## Struktur Direktori

```
dcm4chee-docker/
├── docker-compose.yml   # Konfigurasi container
├── storage/             # Storage DICOM (otomatis dibuat)
├── SKILL.md            # Skill dokumentasi
└── README.md           # File ini
```

## Prerequisites

- Docker Engine 24.0+
- Docker Compose v2
- Port yang tersedia: `389`, `5432`, `8080`, `9990`, `11112`
- RAM minimal 4GB (disarankan 8GB+)

## Cara Install & Run

### 1. Start Services

```bash
cd dcm4chee-docker
docker-compose up -d
```

### 2. Tunggu sampai semua service healthy

```bash
docker-compose ps
```

### 3. Akses Web UI

```
URL:    http://localhost:8080/dcm4chee-arc/ui2
User:   root
Pass:   changeit
```

### 4. Cek Logs

```bash
docker-compose logs -f arc
```

## Stop / Restart

```bash
# Stop
docker-compose down

# Stop + hapus data
docker-compose down -v

# Restart
docker-compose restart
```

## Koneksi DICOM

| Parameter | Value |
|-----------|-------|
| AE Title | DCM4CHEE |
| Host | localhost |
| Port | 11112 |
| Fetch/Store/C-Move AE | DCM4CHEE |

## Troubleshooting

### Container tidak start
```bash
docker logs dcm4chee-arc
```

### Cek database connection
```bash
docker exec dcm4chee-db pg_isready -U dcm4chee
```

### Cek LDAP connection
```bash
docker exec dcm4chee-ldap ldapwhoami -x -H ldap://localhost -D "cn=admin,dc=dcm4che,dc=org" -w changeit
```

### Reset semua data
```bash
docker-compose down -v --remove-orphans
docker system prune -f
docker-compose up -d
```