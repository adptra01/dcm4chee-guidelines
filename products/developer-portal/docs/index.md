---
layout: home

hero:
  name: ORP Developer Portal
  text: Open Radiology Platform
  tagline: RIS · OMC · PACS · Viewer · AI — dokumentasi untuk pengembang
  actions:
    - theme: brand
      text: Arsitektur
      link: /architecture
    - theme: alt
      text: API Produk
      link: /products/omc
    - theme: alt
      text: Kontribusi
      link: /contributing

features:
  - title: RIS (Laravel)
    details: Pasien, order, laporan, audit. Source of truth data klinis.
  - title: OMC (FastAPI + Svelte)
    details: Open Modality Console — pengganti PZDR. Workflow modality.
  - title: PACS (Orthanc)
    details: Satu-satunya penyimpan DICOM (Core Rule, ADR-003).
  - title: AI Service (FastAPI)
    details: Analisis citra dari Orthanc (statistik v1, ML menyusul).
  - title: Integration (FastAPI)
    details: MORBIS · MWL SCP · FHIR · HL7 — penerjemah kontrak SIMRS.
  - title: dicom-core
    details: Paket inti DICOM — parse, preview PNG, C-ECHO/C-STORE.
---

## Postman Collection

Import langsung ke Postman (base URL default `http://localhost:8000`, sesuaikan per service):

- [OMC](https://raw.githubusercontent.com/adptra01/open-radiology-platform/main/docs/api/postman/omc.postman-collection.json) — 6 endpoint (import, preview, queue, store, settings)
- [AI](https://raw.githubusercontent.com/adptra01/open-radiology-platform/main/docs/api/postman/ai.postman-collection.json) — 3 endpoint (health, analyze instance/series)
- [Integration](https://raw.githubusercontent.com/adptra01/open-radiology-platform/main/docs/api/postman/integration.postman-collection.json) — 5 endpoint (MORBIS, HL7, FHIR)

Generate ulang: `python3 scripts/gen-postman.py <nama> <url-openapi>` (stdlib, tanpa dependency).
