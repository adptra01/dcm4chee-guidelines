# ORP AI — AI Service

Produk AI: inferensi & CAD untuk citra radiologi. Membaca studi dari Orthanc,
**tidak mengubah gambar** — memberi saran (suggestion) (ADR-001).

## Stack
- **api/** — FastAPI (`GET /health`, inferensi API)
- **workers/** — job processing (inference, segmentation, measurement)
- **models/** — model (PyTorch/MONAI/OpenCV)

## Batasan
- ❌ Tidak menyimpan DICOM (baca dari Orthanc via DICOMweb)
- ✅ Hasil AI: saran/annotasi, bukan modifikasi gambar

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | Health, Inference API |
| v0.2 | MONAI |
| v0.3 | CAD (bone/lung/fracture detection) |

## Jalankan
```bash
cd products/ai/api && docker compose up
```
