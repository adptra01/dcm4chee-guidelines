# ADR-004 — Adapter Pattern untuk Modalitas & PACS

- Status: **Accepted** (2026-08-05)
- Konteks: hari ini hanya ada PZDR (modalitas) dan Orthanc (PACS). Besok bisa
  ada CR/CT/USG dan PACS lain. Core tidak boleh berubah saat adapter bertambah.

## Keputusan

1. **Modality adapter** (`products/integration/morbis`, dst) — menerjemahkan
   kontrak SIMRS/RIS eksternal ke kontrak internal. Core (Laravel RIS) tidak
   berubah saat SIMRS baru masuk.
2. **PACS adapter** — semua akses gambar via antarmuka **Orthanc REST /
   DICOMweb** saja (ADR-003). Jika suatu saat perlu dcm4chee/PACS lain, cukup
   adapter baru; aplikasi inti tidak diubah.
3. **DICOM tetap di OMC** — komunikasi DICOM (C-ECHO/C-STORE/MWL/MPPS) selalu
   di `products/omc/api`; modalitas vendor hanya tahu DICOM.

## Alasan

- "PZDR hari ini, vendor lain besok" tanpa menulis ulang inti.
- Adapter adalah penerjemah kontrak, bukan penyimpan data (ADR-001).

## Konsekuensi

- Kontrak API internal harus stabil & ter-versioning (lihat `docs/api/`).
- Adapter baru tidak menyentuh core — hanya menambah satu folder di
  `products/integration/`.

## Lihat juga

- ADR-001 (arsitektur), ADR-003 (DICOM storage)
