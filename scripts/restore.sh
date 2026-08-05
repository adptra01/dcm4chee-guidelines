#!/usr/bin/env bash
# restore.sh — restore backup ORP (storage DICOM + index PostgreSQL)
#
# ⚠️ DESTRUKTIF: menimpa data yang ada di data/orthanc dan volume pacs-db-data.
# Disabled-by-default — butuh flag --run secara eksplisit.
# Restore TIDAK dijalankan otomatis oleh CI.
#
# Penggunaan:
#   scripts/restore.sh --run data/backups/orp-backup-<stamp>.tar.gz data/backups/postgres-<stamp>.sql
set -euo pipefail

# --- Load .env ---
if [ -f .env ]; then set -a; source .env; set +a; fi
POSTGRES_DB="${POSTGRES_DB:-orthanc}"
POSTGRES_USER="${POSTGRES_USER:-orthanc}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-orthanc}"

if [ "${1:-}" != "--run" ]; then
  echo "⛔ restore bersifat DESTRUKTIF dan disabled-by-default."
  echo "   Jalankan dengan flag eksplisit:"
  echo "   scripts/restore.sh --run <storage.tar.gz> <postgres.sql>"
  exit 1
fi
shift

STORAGE_ARCHIVE="${1:?storage tar.gz wajib}"
PG_DUMP="${2:?postgres sql wajib}"

[ -f "${STORAGE_ARCHIVE}" ] || { echo "File tidak ditemukan: ${STORAGE_ARCHIVE}"; exit 1; }
[ -f "${PG_DUMP}" ] || { echo "File tidak ditemukan: ${PG_DUMP}"; exit 1; }

echo "== Restore ORP (DESTRUKTIF) =="
echo "  storage : ${STORAGE_ARCHIVE}"
echo "  postgres: ${PG_DUMP}"
echo "  Menimpa data yang ada. Lanjut? (yes/no)"
read -r CONFIRM
[ "${CONFIRM}" = "yes" ] || { echo "Dibatalkan."; exit 1; }

# 1. Hentikan orthanc (pakai db untuk restore index)
echo "  [1/4] stop orthanc ..."
docker compose stop orthanc > /dev/null

# 2. Restore storage DICOM
echo "  [2/4] restore storage DICOM ..."
rm -rf data/orthanc
mkdir -p data/orthanc
tar xzf "${STORAGE_ARCHIVE}" -C data

# 3. Restore index PostgreSQL
echo "  [3/4] restore index PostgreSQL ..."
docker exec -e PGPASSWORD="${POSTGRES_PASSWORD}" pacs-db \
  psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" > /dev/null
docker exec -i -e PGPASSWORD="${POSTGRES_PASSWORD}" pacs-db \
  psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" < "${PG_DUMP}"

# 4. Nyalakan kembali
echo "  [4/4] start orthanc ..."
docker compose start orthanc > /dev/null

echo ""
echo "Restore selesai. Verifikasi:"
echo "  scripts/check.sh"
echo "  curl http://localhost:${REST_PORT:-8042}/studies"
