# Backlog Release v0.4 — Viewer Terintegrasi

Sumber: `tasks/prd-viewer.md` + `tasks/prd-pacs.md`. Kriteria kelulusan: studi dari RIS bisa dibuka & dicari di viewer (OHIF) end-to-end; PACS monitoring opsional.

## Status: MVP viewer sudah terpenuhi (tanpa kode baru)

- [x] Embed OHIF (:3000, DICOMweb→Orthanc)
- [x] Launch Study: `/viewer?StudyInstanceUIDs=…` (200)
- [x] Study Search: study list bawaan OHIF (`showStudyList: true`)
- [x] Launch dari RIS: tombol "Buka di viewer →" (R6, study_uid via MPPS/PATCH)
- [x] E2E: ORD-001 ↔ studi `1.2.276.0.7230010.3.1.2.5.20250903202455` (verified, 200)

## Kerja tersisa (opsional/paralel)

| ID | Story | PRD ref | Prioritas |
|---|---|---|---|
| V1 | Plugin/route khusus ORP di viewer (mis. warna, branding) | prd-viewer FR | P3 |
| V2 | Annotation & measurement (OHIF extension) | US-VIEW-003 | P2 (v0.6) |
| B2 | Dashboard monitoring PACS (health 5 check + disk usage) | US-PACS-002 | P2 |
| D1 | OpenAPI/Postman collection autogen (Developer Platform) | US-DEV-001 | P2 (paralel) |

## Verifikasi release v0.4

- [ ] Buka studi dari RIS → OHIF render (tanpa error di console)
- [ ] Study list OHIF menampilkan 2 studi dev
- [ ] Test total tetap ≥ 102
- [ ] Tag `v0.4.0` + CHANGELOG

## Di luar scope v0.4

- Annotation/measurement (v0.6, V2 viewer)
- AI overlay di viewer (v0.8+, V3)
- MPPS outbound dari OMC (v0.5, V2 OMC)
- Reporting template & signature (v0.6)
