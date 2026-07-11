#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Backup script untuk stack DCM4CHEE
# Backup: PostgreSQL (pacsdb) + MariaDB (Keycloak) + storage DICOM
# ============================================================

# --- Konfigurasi (sesuaikan dengan environment kamu) ---
BACKUP_ROOT="/mnt/backup/pacs"                 # tujuan backup, idealnya disk/volume terpisah dari storage utama
RETENTION_DAYS=14                              # berapa lama backup lama disimpan sebelum dihapus

PG_CONTAINER="db"                              # nama container PostgreSQL PACS
PG_DB="pacsdb"
PG_USER="pacs"

MARIADB_CONTAINER="mariadb"                    # nama container MariaDB Keycloak
MARIADB_DB="keycloak"
MARIADB_USER="keycloak"
MARIADB_PASSWORD="${MARIADB_PASSWORD:-changeme}"   # sebaiknya di-inject via env var, jangan hardcode di produksi

DICOM_STORAGE_DIR="/var/local/dcm4chee-arc/storage"  # path storage DICOM di host

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEST_DIR="${BACKUP_ROOT}/${TIMESTAMP}"

echo "=== Backup PACS dimulai: ${TIMESTAMP} ==="
mkdir -p "${DEST_DIR}"

# --- 1. Backup PostgreSQL (metadata studi, pasien, worklist) ---
echo "[1/3] Backup PostgreSQL (${PG_DB})..."
docker exec "${PG_CONTAINER}" pg_dump -U "${PG_USER}" -F c -d "${PG_DB}" \
  > "${DEST_DIR}/pacsdb_${TIMESTAMP}.dump"
echo "  -> ${DEST_DIR}/pacsdb_${TIMESTAMP}.dump"

# --- 2. Backup MariaDB (Keycloak: user, role, realm config) ---
echo "[2/3] Backup MariaDB (${MARIADB_DB})..."
docker exec "${MARIADB_CONTAINER}" \
  mariadb-dump -u "${MARIADB_USER}" -p"${MARIADB_PASSWORD}" "${MARIADB_DB}" \
  > "${DEST_DIR}/keycloak_${TIMESTAMP}.sql"
echo "  -> ${DEST_DIR}/keycloak_${TIMESTAMP}.sql"

# --- 3. Backup storage DICOM (file gambar aktual) ---
# Pakai rsync agar incremental-friendly; untuk full snapshot pertama akan lama,
# selanjutnya hanya delta yang berubah.
echo "[3/3] Sinkronisasi storage DICOM..."
mkdir -p "${BACKUP_ROOT}/storage_mirror"
rsync -a --delete "${DICOM_STORAGE_DIR}/" "${BACKUP_ROOT}/storage_mirror/"
echo "  -> mirror storage diperbarui di ${BACKUP_ROOT}/storage_mirror/"

# Simpan juga snapshot list file per timestamp ini (untuk audit, bukan copy penuh)
find "${BACKUP_ROOT}/storage_mirror/" -type f > "${DEST_DIR}/storage_filelist_${TIMESTAMP}.txt"

# --- Retention: hapus folder dump DB yang lebih tua dari RETENTION_DAYS ---
echo "Membersihkan backup DB lebih tua dari ${RETENTION_DAYS} hari..."
find "${BACKUP_ROOT}" -maxdepth 1 -type d -name "20*" -mtime +${RETENTION_DAYS} -print -exec rm -rf {} \;

echo "=== Backup selesai: ${TIMESTAMP} ==="
echo ""
echo "PENTING: backup ini baru berguna kalau sudah pernah di-restore-test."
echo "Jalankan restore_test.sh secara berkala untuk verifikasi integritas."
