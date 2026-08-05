# OMC Console — SvelteKit

Workstation UI untuk **Open Modality Console** (pengganti PZDR).

## Menu (roadmap)
Dashboard · Worklist · Queue · Viewer (`/viewer/:studyUID`, embed OHIF) · Settings

## Jalankan
```bash
npm install
npm run dev
# buka http://localhost:5173
```

## Build produksi
```bash
npm run build && npm run preview
```

## Integrasi
- Backend: OMC API (`../api`, FastAPI)
- Viewer: OHIF via `platform/ohif` (embed di `/viewer/:studyUID`)
- PACS: Orthanc (DICOMweb) — **console tidak tahu lokasi file DICOM** (ADR-003)
