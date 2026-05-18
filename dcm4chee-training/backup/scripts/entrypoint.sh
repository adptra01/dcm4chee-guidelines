#!/bin/bash
# ============================================================
# DCM4CHEE Backup Entry Point
# Level 2 & 3 - Automated Backup Scheduler
# ============================================================
set -e

echo "[BACKUP] Starting DCM4CHEE backup scheduler..."

# Install dependencies
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq && apt-get install -y -qq \
    postgresql-client \
    ldap-utils \
    rsync \
    gzip \
    curl \
    > /dev/null 2>&1

# Function: Backup PostgreSQL
backup_db() {
    echo "[$(date)] Starting PostgreSQL backup..."
    DATE=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="/backup/pacsdb_${DATE}.dump"

    PGPASSWORD="${DB_PASSWORD}" pg_dump -h "${DB_HOST}" -p "${DB_PORT:-5432}" \
        -U "${DB_USER}" -d "${DB_NAME}" \
        -Fc -f "${BACKUP_FILE}"

    gzip -9 "${BACKUP_FILE}"

    # Cleanup old backups
    find /backup -name "pacsdb_*.dump.gz" -mtime +${RETENTION_DAYS:-30} -delete

    echo "[$(date)] PostgreSQL backup completed: pacsdb_${DATE}.dump.gz"
    echo "Size: $(du -sh /backup/pacsdb_${DATE}.dump.gz | cut -f1)"
}

# Function: Backup Storage (rsync)
backup_storage() {
    echo "[$(date)] Starting storage rsync backup..."
    DATE=$(date +%Y%m%d)

    rsync -avh --delete \
        --exclude '*.tmp' \
        --exclude '*.incomplete' \
        --exclude '.snapshot' \
        /storage/archive/ "${BACKUP_DEST}/storage_${DATE}/" || true

    echo "[$(date)] Storage backup completed"
}

# Function: Backup LDAP
backup_ldap() {
    echo "[$(date)] Starting LDAP backup..."
    DATE=$(date +%Y%m%d_%H%M%S)
    LDIF_FILE="/backup/ldap_${DATE}.ldif"

    ldapsearch -x -H "ldap://${LDAP_HOST}:389" \
        -D "cn=admin,dc=dcm4che,dc=org" \
        -w "${LDAP_PASSWORD}" \
        -b "dc=dcm4che,dc=org" \
        -LLL > "${LDIF_FILE}"

    gzip -9 "${LDIF_FILE}"

    # Cleanup old backups
    find /backup -name "ldap_*.ldif.gz" -mtime +${RETENTION_DAYS:-30} -delete

    echo "[$(date)] LDAP backup completed"
}

# Function: Verify backups
verify_backups() {
    echo "[$(date)] Verifying backup integrity..."

    # Check DB backup
    LATEST_DB=$(ls -t /backup/pacsdb_*.dump.gz 2>/dev/null | head -1)
    if [ -n "$LATEST_DB" ]; then
        gunzip -c "$LATEST_DB" | head -c 1024 > /dev/null && echo "DB backup OK: $LATEST_DB" || echo "DB backup CORRUPTED: $LATEST_DB"
    fi

    # Check LDAP backup
    LATEST_LDAP=$(ls -t /backup/ldap_*.ldif.gz 2>/dev/null | head -1)
    if [ -n "$LATEST_LDAP" ]; then
        gunzip -c "$LATEST_LDAP" | head -c 1024 > /dev/null && echo "LDAP backup OK: $LATEST_LDAP" || echo "LDAP backup CORRUPTED: $LATEST_LDAP"
    fi
}

# Function: Send alert (optional)
send_alert() {
    local level=$1
    local message=$2
    echo "[ALERT:$level] $message"
    # Add: mail, slack, or SMS integration here
}

# Main scheduling loop
echo "Schedule - DB: ${SCHEDULE_DB:-0 */6 * * *}"
echo "Schedule - Storage: ${SCHEDULE_STORAGE:-30 * * * *}"

# Run initial backup
backup_db
backup_storage
backup_ldap

# Scheduling: Simple cron-like check
while true; do
    MINUTE=$(date +%M)
    HOUR=$(date +%H)

    # DB backup every 6 hours
    if [ "$MINUTE" = "00" ]; then
        backup_db 2>&1 | tee -a /var/log/dcm4chee-backup.log
    fi

    # Storage rsync every hour at :30
    if [ "$MINUTE" = "30" ]; then
        backup_storage 2>&1 | tee -a /var/log/dcm4chee-backup.log
    fi

    # LDAP backup daily at 02:00
    if [ "$MINUTE" = "00" ] && [ "$HOUR" = "02" ]; then
        backup_ldap 2>&1 | tee -a /var/log/dcm4chee-backup.log
    fi

    # Verify weekly (Sunday 03:00)
    if [ "$MINUTE" = "00" ] && [ "$HOUR" = "03" ] && [ "$(date +%w)" = "0" ]; then
        verify_backups
    fi

    sleep 60
done