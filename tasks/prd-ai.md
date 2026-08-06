# PRD: ORP AI Platform

## Introduction

Produk analisis & AI medis. Terpisah dari OMC. Membaca studi dari PACS (Orthanc), menghasilkan analisis/overlay/measurement, dan menyediakan inference API yang dipakai Viewer (AI overlay) & RIS (mendukung diagnosis). MVP: inference API + model statistik yang sudah ada (jangan over-build).

## Goals

- Inference API: input studi → hasil terstruktur
- Terintegrasi dengan Orthanc (input studi) & Viewer (output overlay)
- Health endpoint
- Multi engine: statistik-v1 (ada) + model ML (V2), terisolasi satu sama lain

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover:
- AI Service `products/ai`: engine `statistik-v1`, membaca dari Orthanc (VOI LUT → mean/std/percentile + finding) — MS7
- Test 5

Belum tercover: inference API publik terstruktur, model ML (MONAI), segmentation, overlay, pipeline training.

## Fase

- **MVP (v0.8):** Health, Inference API, Dummy Model
- **V2:** MONAI, Torch, Segmentation
- **V3:** CAD, Multi Model, Federated Learning

## User Stories

### US-AI-001: Health endpoint
**Description:** Sebagai developer, saya ingin endpoint health untuk memastikan AI service hidup.

**Acceptance Criteria:**
- [ ] `GET /health` return 200 + status
- [ ] Test lulus

### US-AI-002: Inference API v1
**Description:** Sebagai developer, saya ingin memanggil analisis citra secara terstruktur (menerima StudyInstanceUID, return metrics + finding) agar bisa dipakai Viewer/integration.

**Acceptance Criteria:**
- [ ] `POST /inference` menerima StudyInstanceUID
- [ ] Baca studi dari Orthanc (input)
- [ ] Output: mean/std/percentile + finding (engine statistik-v1 saat ini)
- [ ] Response JSON terstruktur
- [ ] Test lulus

### US-AI-003: Dummy model ML
**Description:** Sebagai developer, saya ingin satu model ML sederhana berjalan di endpoint terpisah agar arsitektur multi-engine terbukti.

**Acceptance Criteria:**
- [ ] Endpoint `/inference/monai` (atau sejenis) dengan model dummy (mis. klasifikasi sederhana)
- [ ] Terisolasi dari engine statistik-v1
- [ ] Test lulus

## Functional Requirements

- FR-1: `GET /health` tersedia
- FR-2: `POST /inference` menerima StudyInstanceUID, fetch dari Orthanc, output metrics/finding JSON
- FR-3: Engine statistik-v1 tetap berjalan; engine ML terisolasi (multi engine)
- FR-4: Penambahan model baru tidak mengganggu statistik-v1
- FR-5: Kredensial/endpoint Orthanc via env, tidak hardcode
- FR-6: Output JSON tidak mengekspos pixel blob kecuali diminta (overlay/thumbnail)

## Non-Goals

- Tidak mengerjakan rendering citra di produk ini (Viewer)
- Tidak ada UI training/dataset manager di MVP
- Tidak ada CAD production di MVP
- Tidak melakukan analisis di dalam OMC (OMC hanya kirim gambar)

## Design Considerations

- Engine diorganisir sebagai adapter; statistik-v1 = engine default
- Overlay hasil AI (V2) disajikan lewat Viewer, bukan render inline

## Technical Considerations

- Python (FastAPI) — konsisten dengan OMC/integration
- Input dari Orthanc REST 8042 (fetch DICOM)
- ML stack: MONAI + PyTorch (V2)
- Inference per studi, tidak stateful antar request

## Success Metrics

- 5 test AI → target 10+ setelah inferensi + health
- Latency inferensi statistik < 2 detik/studi di dev
- Output terstruktur tanpa error pada studi sample

## Auth (ADR-006)

- **Wajib sebelum produksi:** middleware `X-API-Key` (pola Integration MS12). Kredensial via `.env`, tidak hardcode (SECURITY.md).

## Open Questions

- Engine mana di MVP: statistik (sudah ada) atau dummy MONAI?
- Overlay hasil AI ke viewer: via DICOM SR atau API query?
- Prioritas model V2: klasifikasi atau segmentation?
