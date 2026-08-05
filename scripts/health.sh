#!/usr/bin/env bash
# health.sh — status ringkas per komponen (cocok untuk cron / dashboard)
# Penggunaan:  scripts/health.sh  [--json]
set -euo pipefail

if [ -f .env ]; then set -a; source .env; set +a; fi
HOST="${HOST:-localhost}"; REST_PORT="${REST_PORT:-8042}"; OHIF_PORT="${OHIF_PORT:-3000}"

json_mode=false
[ "${1:-}" = "--json" ] && json_mode=true

if $json_mode; then
  echo "{"
  echo "  \"containers\": {"
  docker compose ps --format '{{.Name}}|{{.Status}}' | while IFS='|' read -r name status; do
    printf '    "%s": "%s",\n' "${name}" "${status}"
  done | sed '$ s/,$//'
  echo "  },"

  ORTHANC_HTTP=$(curl -sf -o /dev/null -w "%{http_code}" "http://${HOST}:${REST_PORT}/system" || echo 000)
  OHIF_HTTP=$(curl -sf -o /dev/null -w "%{http_code}" "http://${HOST}:${OHIF_PORT}/" || echo 000)
  STUDIES=$(curl -sf "http://${HOST}:${REST_PORT}/studies" 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "?")
  printf '  "orthanc_http": %s,\n' "$ORTHANC_HTTP"
  printf '  "ohif_http": %s,\n' "$OHIF_HTTP"
  printf '  "studies": %s\n' "$STUDIES"
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
