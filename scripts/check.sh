#!/usr/bin/env bash
# check.sh — healthcheck menyeluruh stack ORP (platform/)
# Memverifikasi: container hidup · REST Orthanc · DICOM C-ECHO · DICOMweb · OHIF
#
# Penggunaan:  scripts/check.sh
# Exit code:   0 = semua sehat, 1 = ada yang gagal
set -euo pipefail

# --- Load .env bila ada ---
if [ -f .env ]; then set -a; source .env; set +a; fi
HOST="${HOST:-localhost}"
DICOM_PORT="${DICOM_PORT:-4242}"
REST_PORT="${REST_PORT:-8042}"
OHIF_PORT="${OHIF_PORT:-3000}"
AE_TITLE="${ORTHANC_AE_TITLE:-ORTHANC}"

PASS=0; FAIL=0
ok()   { echo "  [OK] $1"; PASS=$((PASS+1)); }
bad()  { echo "  [!!] $1"; FAIL=$((FAIL+1)); }

echo "== Container (docker compose ps) =="
if docker compose ps --format '{{.Name}} {{.Status}}' | grep -q "pacs-orthanc.*healthy"; then
  ok "orthanc healthy"
else
  bad "orthanc tidak healthy — cek: docker compose ps / logs"
fi

echo "== REST Orthanc (:${REST_PORT}) =="
if curl -sf "http://${HOST}:${REST_PORT}/system" > /dev/null; then
  ok "REST /system"
else
  bad "REST /system tidak merespons"
fi

echo "== Studi di PACS =="
STUDIES=$(curl -sf "http://${HOST}:${REST_PORT}/studies" 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "?")
echo "  studi terdaftar: ${STUDIES}"

echo "== DICOMweb QIDO-RS =="
if curl -sf "http://${HOST}:${REST_PORT}/dicom-web/studies" > /dev/null; then
  ok "QIDO-RS /dicom-web/studies"
else
  bad "QIDO-RS tidak merespons"
fi

echo "== C-ECHO (DICOM :${DICOM_PORT}) =="
if command -v echoscu > /dev/null; then
  if echoscu -aec "${AE_TITLE}" -aet ORP_CHECK "127.0.0.1" "${DICOM_PORT}" > /dev/null 2>&1; then
    ok "C-ECHO ${AE_TITLE}"
  else
    bad "C-ECHO gagal (AE=${AE_TITLE} port=${DICOM_PORT})"
  fi
else
  echo "  (skipped C-ECHO — echoscu (dcmtk) tidak terpasang di mesin ini)"
fi

echo "== OHIF Viewer (:${OHIF_PORT}) =="
if curl -sf "http://${HOST}:${OHIF_PORT}/" > /dev/null; then
  ok "OHIF ${HOST}:${OHIF_PORT}"
else
  bad "OHIF tidak merespons"
fi

echo ""
echo "Hasil: ${PASS} OK, ${FAIL} gagal"
[ "${FAIL}" -eq 0 ] || exit 1
