# ORP Viewer — Viewer Integrasi

Produk Viewer: integrasi OHIF untuk menampilkan studi. Dibuka dari OMC console
via route `/viewer/:studyUID` (keputusan: embed, satu kesatuan UI).

## Stack
- **ohif/** — konfigurasi & deploy OHIF (`ohif/app:latest` di `platform/`)
- **plugins/** — plugin/annotation khusus ORP

## Batasan
- ✅ Semua gambar selalu dari **Orthanc** (DICOMweb) — tidak ada salinan lokal
- Viewer tidak tahu lokasi file DICOM (ADR-001 Core Rule)

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | View studi dari OMC |
| v0.2 | Annotation |
| v0.3 | Plugin ekstensi |

## Jalankan
```bash
# OHIF tersedia via compose root:
docker compose up ohif
```
