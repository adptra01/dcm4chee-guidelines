#!/usr/bin/env bash
# backup.sh — backup storage DICOM (data/orthanc) + index PostgreSQL (pacs-db)
# Hasil: file .tar.gz ke BACKUP_DIR (default: data/backups/)
#
# Penggunaan:  scripts/backup.sh
set -euo pipefail

# --- Load .env ---
if [ -f .env ]; then set -a; source .env; set +a; fi
POSTGRES_DB="${POSTGRES_DB:-orthanc}"
POSTGRES_USER="${POSTGRES_USER:-orthanc}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-orthanc}"
BACKUP_DIR="${BACKUP_DIR:-data/backups}"

mkdir -p "${BACKUP_DIR}"
STAMP=$(date '+%Y%m%d-%H%M%S')
ARCHIVE="${BACKUP_DIR}/orp-backup-${STAMP}.tar.gz"

echo "== Backup ORP → ${ARCHIVE} =="

# 1. Storage DICOM (file gambar) — hanya bila ada
if [ -d "data/orthanc" ]; then
  echo "  [1/2] archive storage DICOM (data/orthanc) ..."
  tar czf "${ARCHIVE}" -C data orthanc
else
  echo "  [1/2] data/orthanc tidak ada — lewati storage"
  tar czf "${ARCHIVE}" --files-from /dev/null
fi

# 2. Index PostgreSQL (metadata + studi)
echo "  [2/2] dump index PostgreSQL (${POSTGRES_DB}) ..."
docker exec -e PGPASSWORD="${POSTGRES_PASSWORD}" pacs-db \
  pg_dump -U "${POSTGRES_USER}" "${POSTGRES_DB}" > "${BACKUP_DIR}/postgres-${STAMP}.sql"

echo ""
echo "Selesai:"
echo "  storage : ${ARCHIVE}"
echo "  postgres: ${BACKUP_DIR}/postgres-${STAMP}.sql"
ls -lh "${ARCHIVE}" "${BACKUP_DIR}/postgres-${STAMP}.sql"
echo ""
echo "Simpan kedua file bersama-sama (konsisten untuk restore)."
