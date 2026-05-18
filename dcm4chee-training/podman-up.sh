#!/bin/bash
# ============================================================
# DCM4CHEE Archive 5.x - Podman Quick Start Script
# Level 1: Basic (RS Kecil - 1-2 Modality)
# ============================================================
# Usage:
#   cd dcm4chee-training
#   chmod +x podman-up.sh
#   ./podman-up.sh
#
# Access setelah startup:
#   Web UI:  http://localhost:8080/dcm4chee-arc/ui2
#   User:   root
#   Pass:   changeit
#   DICOM:  localhost:11112
# ============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="dcm4chee-arc"
SUBNET="172.28.0.0/16"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[DCM4CHEE]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ─────────────────────────────────────────────────────────
# Create Podman network
# ─────────────────────────────────────────────────────────
create_network() {
    log "Creating Podman network..."
    if podman network inspect dcm4chee-net >/dev/null 2>&1; then
        warn "Network 'dcm4chee-net' already exists"
    else
        podman network create --subnet="$SUBNET" dcm4chee-net
        log "Network created: $SUBNET"
    fi
}

# ─────────────────────────────────────────────────────────
# Create volumes
# ─────────────────────────────────────────────────────────
create_volumes() {
    log "Creating volumes..."
    for vol in ldap_data db_data storage_data wildfly_data; do
        if podman volume inspect "dcm4chee_${vol}" >/dev/null 2>&1; then
            warn "Volume 'dcm4chee_${vol}' already exists"
        else
            podman volume create "dcm4chee_${vol}"
        fi
    done
}

# ─────────────────────────────────────────────────────────
# Pull images
# ─────────────────────────────────────────────────────────
pull_images() {
    log "Pulling DCM4CHEE images..."
    podman pull docker.io/dcm4che/slapd-dcm4chee:2.6.10-34.2
    podman pull docker.io/dcm4che/postgres-dcm4chee:17.4-34
    podman pull docker.io/dcm4che/dcm4chee-arc-psql:5.34.2
    log "Images pulled successfully"
}

# ─────────────────────────────────────────────────────────
# Start LDAP
# ─────────────────────────────────────────────────────────
start_ldap() {
    log "Starting OpenLDAP..."
    podman run -d \
        --name dcm4chee-ldap \
        --network dcm4chee-net \
        --hostname ldap \
        -p 389:389 \
        -e SLAPD_PASSWORD=secret \
        -e LDAP_ROOTPASS=secret \
        -e LDAP_CONFIGPASS=secret \
        -e SLAPD_DOMAIN=dcm4che.org \
        -e SLAPD_ORGANIZATION=dcm4che \
        -e STORAGE_DIR=/storage/fs1 \
        -v dcm4chee_ldap_data:/var/lib/openldap/openldap-data \
        -v dcm4chee_ldap_config:/etc/openldap/slapd.d \
        --health-start-period 60s \
        --health-cmd "ldapwhoami -x -H ldap://localhost -D cn=admin,dc=dcm4che,dc=org -w secret" \
        --health-interval 30s \
        --health-timeout 10s \
        --health-retries 5 \
        --restart unless-stopped \
        docker.io/dcm4che/slapd-dcm4chee:2.6.10-34.2

    log "Waiting for LDAP to be healthy..."
    for i in $(seq 1 30); do
        sleep 2
        if podman healthcheck inspect dcm4chee-ldap 2>/dev/null | grep -q "healthy"; then
            log "LDAP is healthy"
            return 0
        fi
        echo -n "."
    done
    warn "LDAP health check not passed yet, continuing..."
}

# ─────────────────────────────────────────────────────────
# Start PostgreSQL
# ─────────────────────────────────────────────────────────
start_db() {
    log "Starting PostgreSQL..."
    podman run -d \
        --name dcm4chee-db \
        --network dcm4chee-net \
        --hostname db \
        -p 5432:5432 \
        -e POSTGRES_DB=pacsdb \
        -e POSTGRES_USER=pacs \
        -e POSTGRES_PASSWORD=pacs \
        -v dcm4chee_db_data:/var/lib/postgresql/data \
        --health-start-period 60s \
        --health-cmd "pg_isready -U pacs -d pacsdb" \
        --health-interval 30s \
        --health-timeout 10s \
        --health-retries 5 \
        --restart unless-stopped \
        docker.io/dcm4che/postgres-dcm4chee:17.4-34

    log "Waiting for PostgreSQL to be healthy..."
    for i in $(seq 1 30); do
        sleep 2
        if podman healthcheck inspect dcm4chee-db 2>/dev/null | grep -q "healthy"; then
            log "PostgreSQL is healthy"
            return 0
        fi
        echo -n "."
    done
    warn "PostgreSQL health check not passed yet, continuing..."
}

# ─────────────────────────────────────────────────────────
# Start Archive
# ─────────────────────────────────────────────────────────
start_arc() {
    log "Starting DCM4CHEE Archive..."
    podman run -d \
        --name dcm4chee-arc \
        --network dcm4chee-net \
        --hostname arc \
        -p 8080:8080 \
        -p 8443:8443 \
        -p 9990:9990 \
        -p 9993:9993 \
        -p 11112:11112 \
        -p 2762:2762 \
        -p 2575:2575 \
        -e POSTGRES_DB=pacsdb \
        -e POSTGRES_USER=pacs \
        -e POSTGRES_PASSWORD=pacs \
        -e ARCHIVE_STORAGE_DIR=/storage/archive \
        -e LDAP_URL=ldap://ldap:389 \
        -e LDAP_BASE_DN=dc=dcm4che,dc=org \
        -e LDAP_USER=cn=admin,dc=dcm4che,dc=org \
        -e LDAP_PASSWORD=secret \
        -e LDAP_ROOTPASS=secret \
        -e "WILDFLY_WAIT_FOR=ldap:389 db:5432" \
        -e WILDFLY_CHOWN=/storage \
        -e WILDFLY_JAVA_OPTS="-Xms1g -Xmx2g -XX:+UseG1GC" \
        -v dcm4chee_storage_data:/storage/archive \
        -v dcm4chee_wildfly_data:/opt/wildfly/standalone \
        --health-start-period 300s \
        --health-cmd "curl -kf http://localhost:8080/dcm4chee-arc/ui2/" \
        --health-interval 60s \
        --health-timeout 30s \
        --health-retries 10 \
        --restart unless-stopped \
        docker.io/dcm4che/dcm4chee-arc-psql:5.34.2

    log "Archive started. First startup takes 3-10 minutes for WildFly deployment."
}

# ─────────────────────────────────────────────────────────
# Wait for archive healthy
# ─────────────────────────────────────────────────────────
wait_for_arc() {
    log "Waiting for Archive to be healthy (this may take 3-10 minutes)..."
    echo "   WildFly perlu deploy archive application pertama kali..."
    echo "   Monitor dengan: podman logs -f dcm4chee-arc"
    echo ""

    for i in $(seq 1 60); do
        STATUS=$(podman healthcheck inspect dcm4chee-arc 2>/dev/null | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        if [ "$STATUS" = "healthy" ]; then
            log "DCM4CHEE Archive is healthy!"
            return 0
        fi
        # Also check via HTTP
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/dcm4chee-arc/ui2/ 2>/dev/null)
        if [ "$HTTP_CODE" = "200" ]; then
            log "DCM4CHEE Archive Web UI is responding!"
            return 0
        fi
        sleep 10
        echo -n "."
    done
    warn "Archive health check belum healthy setelah 10 menit."
    warn "Cek logs: podman logs dcm4chee-arc --tail 50"
    warn "Akses Web UI mungkin sudah bisa meskipun healthcheck belum green"
}

# ─────────────────────────────────────────────────────────
# Show status
# ─────────────────────────────────────────────────────────
show_status() {
    echo ""
    echo "══════════════════════════════════════════════════════"
    echo "            DCM4CHEE Archive Status"
    echo "══════════════════════════════════════════════════════"
    echo ""
    podman ps --filter label=application=dcm4chee --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    echo ""

    echo "URLs:"
    echo "  Archive Web UI:  http://localhost:8080/dcm4chee-arc/ui2"
    echo "  WildFly Console: http://localhost:9990"
    echo "  DICOM Port:      localhost:11112"
    echo ""
    echo "Credentials:"
    echo "  User:     root"
    echo "  Password: secret"
    echo ""
    echo "Quick commands:"
    echo "  podman ps                      - cek container"
    echo "  podman logs -f dcm4chee-arc     - lihat logs"
    echo "  podman stop dcm4chee-arc        - stop archive"
    echo "  podman rm dcm4chee-arc          - hapus container"
    echo ""
    echo "To shutdown all:"
    echo "  ./podman-down.sh"
    echo ""
}

# ─────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────
main() {
    echo ""
    echo "╔══════════════════════════════════════════════════════╗"
    echo "║   DCM4CHEE Archive 5.x - Podman Quick Start          ║"
    echo "║   Level 1: Basic (RS Kecil)                         ║"
    echo "╚══════════════════════════════════════════════════════╝"
    echo ""

    create_network
    create_volumes
    pull_images
    start_ldap
    start_db
    start_arc
    wait_for_arc
    show_status
}

main "$@"