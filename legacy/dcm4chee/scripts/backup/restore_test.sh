#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Restore test script — jalankan berkala (mis. tiap minggu)
# untuk membuktikan backup benar-benar bisa dipulihkan,
# bukan sekadar "script jalan tanpa error".
# ============================================================

BACKUP_ROOT="/mnt/backup/pacs"
TEST_PG_CONTAINER="db_restore_test"   # container PostgreSQL sementara khusus uji restore
TEST_DB="pacsdb_restore_test"
PG_USER="pacs"
PG_PASSWORD="${PG_PASSWORD:-pacs}"

# --- Ambil backup dump terbaru ---
LATEST_DIR=$(find "${BACKUP_ROOT}" -maxdepth 1 -type d -name "20*" | sort | tail -n 1)
DUMP_FILE=$(find "${LATEST_DIR}" -name "pacsdb_*.dump" | head -n 1)

if [ -z "${DUMP_FILE}" ]; then
  echo "Tidak ada file dump ditemukan di ${LATEST_DIR}"
  exit 1
fi

echo "Menguji restore dari: ${DUMP_FILE}"

# --- Jalankan container PostgreSQL sementara, terisolasi dari produksi ---
docker rm -f "${TEST_PG_CONTAINER}" 2>/dev/null || true
docker run --name "${TEST_PG_CONTAINER}" -d \
  -e POSTGRES_DB="${TEST_DB}" \
  -e POSTGRES_USER="${PG_USER}" \
  -e POSTGRES_PASSWORD="${PG_PASSWORD}" \
  postgres:17

echo "Menunggu database sementara siap..."
sleep 8

# --- Restore dump ke database sementara ---
docker cp "${DUMP_FILE}" "${TEST_PG_CONTAINER}:/tmp/restore.dump"
docker exec "${TEST_PG_CONTAINER}" \
  pg_restore -U "${PG_USER}" -d "${TEST_DB}" /tmp/restore.dump

# --- Verifikasi dasar: hitung jumlah baris di tabel studi ---
STUDY_COUNT=$(docker exec "${TEST_PG_CONTAINER}" \
  psql -U "${PG_USER}" -d "${TEST_DB}" -t -c "SELECT COUNT(*) FROM study;" | tr -d '[:space:]')

echo ""
echo "=== Hasil verifikasi restore ==="
echo "Jumlah studi ditemukan di backup: ${STUDY_COUNT}"

if [ "${STUDY_COUNT}" -gt 0 ]; then
  echo "STATUS: OK — backup valid dan bisa direstore."
else
  echo "STATUS: GAGAL — backup tidak berisi data studi, cek proses backup!"
fi

# --- Bersihkan container sementara ---
docker rm -f "${TEST_PG_CONTAINER}"
