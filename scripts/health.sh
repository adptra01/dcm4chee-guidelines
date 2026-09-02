#!/usr/bin/env bash
# health.sh — status ringkas per komponen (cocok untuk cron / dashboard)
# 5 check: orthanc, postgres, ohif, disk (storage DICOM), service.
# Penggunaan:  scripts/health.sh  [--json]
set -euo pipefail

if [ -f .env ]; then set -a; source .env; set +a; fi
HOST="${HOST:-localhost}"; REST_PORT="${REST_PORT:-8042}"; OHIF_PORT="${OHIF_PORT:-3000}"
PG_PORT="${PG_PORT:-5432}"; PG_USER="${POSTGRES_USER:-orthanc}"; PG_DB="${POSTGRES_DB:-orthanc}"
STORAGE_DIR="${STORAGE_DIR:-data/orthanc}"

json_mode=false
[ "${1:-}" = "--json" ] && json_mode=true

if $json_mode; then
  echo "{"
  echo "  \"containers\": {"
  docker compose ps --format '{{.Name}}|{{.Status}}' | while IFS='|' read -r name status; do
    printf '    "%s": "%s",\n' "${name}" "${status}"
  done | sed '$ s/,$//'
  echo "  },"

  ORTHANC_HTTP=$(curl -s -o /dev/null -w "%{http_code}" "http://${HOST}:${REST_PORT}/system" 2>/dev/null || true); [ -z "$ORTHANC_HTTP" ] && ORTHANC_HTTP=000
  OHIF_HTTP=$(curl -s -o /dev/null -w "%{http_code}" "http://${HOST}:${OHIF_PORT}/" 2>/dev/null || true); [ -z "$OHIF_HTTP" ] && OHIF_HTTP=000
  STUDIES=$(curl -s "http://${HOST}:${REST_PORT}/studies" 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "?")

  # postgres: cek via docker compose exec pg_isready (port tidak di-expose ke host)
  if docker compose exec -T db pg_isready -U "${PG_USER}" -d "${PG_DB}" >/dev/null 2>&1; then PG_OPEN="up"; else PG_OPEN="down"; fi

  # disk: storage DICOM + disk overall (host)
  if [ -d "${STORAGE_DIR}" ]; then
    DICOM_USED=$(du -sm "${STORAGE_DIR}" 2>/dev/null | awk '{print $1}' || echo 0)
  else
    DICOM_USED=0
  fi
  DISK_JSON=$(df -Pk "${STORAGE_DIR:-/}" 2>/dev/null | awk 'NR==2{printf "{\"used_percent\": %s, \"avail_gb\": %.1f}", $5+0, $4/1048576}' || echo '{"used_percent": 0, "avail_gb": 0}')
  SERVICE_TMP=$(curl -s -o /dev/null -w "%{http_code}" "http://${HOST}:${OHIF_PORT}/" 2>/dev/null || true); [ "$SERVICE_TMP" = "000" ] || [ -z "$SERVICE_TMP" ] && SERVICE_HTTP="down" || SERVICE_HTTP="up"

  echo "  \"orthanc_http\": \"$ORTHANC_HTTP\","
  echo "  \"ohif_http\": \"$OHIF_HTTP\","
  echo "  \"postgres\": \"${PG_OPEN}\","
  echo "  \"service\": \"${SERVICE_HTTP}\","
  echo "  \"studies\": \"${STUDIES}\","
  echo "  \"disk\": {"
  echo "    \"dicom_storage_mb\": ${DICOM_USED},"
  echo "    \"host\": ${DISK_JSON}"
  echo "  }"
  echo "}"
  exit 0
fi

# Mode teks
echo "ORP Health — $(date '+%Y-%m-%d %H:%M:%S')"
echo "────────────────────────────────────"
docker compose ps --format '  {{.Name}}: {{.Status}}'
echo "────────────────────────────────────"
printf '  Orthanc REST :%s → HTTP %s\n' "$REST_PORT" "$(curl -sf -o /dev/null -w '%{http_code}' "http://${HOST}:${REST_PORT}/system" || echo 000)"
printf '  OHIF     :%s → HTTP %s\n' "$OHIF_PORT" "$(curl -sf -o /dev/null -w '%{http_code}' "http://${HOST}:${OHIF_PORT}/" || echo 000)"
STUDIES=$(curl -sf "http://${HOST}:${REST_PORT}/studies" 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "?")
echo "  Studi terdaftar: ${STUDIES}"
