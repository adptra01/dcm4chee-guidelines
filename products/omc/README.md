# ORP OMC — Open Modality Console

Produk OMC: workstation modality — **pengganti PZDR**. Source of truth untuk
workflow teknis modality (queue, worklist cache, AI result) (ADR-001).

## Stack
- **api/** — FastAPI (Modality Controller: import, preview, queue, MWL, MPPS, DICOM)
- **console/** — SvelteKit (UI workstation: dashboard, worklist, queue, viewer)
- **docs/** — kontrak API & roadmap

## Batasan (ADR-001)
- ❌ Tidak menyimpan DICOM permanen (Orthanc satu-satunya penyimpan)
- ✅ DICOM: C-ECHO/C-STORE (SCU), MWL/MPPS (SCP)
- ✅ REST ke Orthanc & Laravel

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | Import, Preview, Queue |
| v0.2 | MWL, MPPS |
| v0.3 | Detector SDK |

## Jalankan
```bash
cd products/omc/api && docker compose up        # FastAPI
cd products/omc/console && npm install && npm run dev   # Svelte
```
