# ADR-006: Kepemilikan Data & Otorisasi Lintas Produk

- **Status:** Accepted
- **Tanggal:** 2026-08-06
- **Menjawab:** inkonsistensi review dokumen (kepemilikan AI result, dua jalur status order, auth lintas produk, tabel dependensi).

## Konteks

Review lintas-dokumen menemukan 3 ambiguitas yang bisa jadi dua sumber kebenaran / race condition:

1. **AI result**: `ARCHITECTURE.md` menyebut OMC memiliki "Queue, Worklist cache, AI result", padahal `prd-ai.md` memisahkan AI Platform dari OMC.
2. **Order status**: dua jalur update — (a) OMC worker setelah C-STORE → update RIS, (b) MPPS N-CREATE/N-SET via Integration → update RIS. Dua penulis status berbeda untuk data sama.
3. **Auth**: RIS punya session auth; Integration punya `X-API-Key`; OMC & AI belum punya auth sama sekali. Tidak ada keputusan SSO RIS↔Viewer.

## Keputusan

### 1. Kepemilikan AI result → AI Platform

- **AI Platform (`products/ai`) adalah satu-satunya pemilik hasil analisis AI** (statistik, overlay, measurement, finding).
- OMC hanya: import → preview → queue → store. OMC **tidak menyimpan hasil AI**. Preview di OMC = PNG sementara untuk QC radiografer, bukan hasil AI.
- Thumbnail/overlay AI dibaca oleh Viewer langsung dari AI Platform (via API), bukan dari OMC.
- Perbaikan `ARCHITECTURE.md`: baris "Queue, Worklist cache, AI result → OMC" diganti: Queue/Worklist cache → OMC; AI result → AI Platform.

### 2. Pemilik status order → RIS, satu penulis eksternal

- **RIS adalah source of truth untuk status order** (`scheduled → in_progress → completed → cancelled`).
- **MPPS (via Integration :4244) adalah satu-satunya penulis eksternal status** — modalitas melapor melalui Integration, Integration PATCH ke RIS. Ini jalur DICOM-native.
- **OMC worker TIDAK menulis status order.** Setelah C-STORE sukses, OMC hanya menandai queue lokal (`stored`). Jika perlu sinkronisasi, OMC memicu MPPS/event, bukan PATCH langsung ke RIS.
- Konsekuensi: dihapus alur "OMC worker → update RIS (status/laporan)" dari `ARCHITECTURE.md`. Jalur ② hanya: OMC → C-STORE → Orthanc → (event) → OMC queue `stored`.
- UI RIS (Volt) juga penulis status (workflow manual petugas) — dua penulis: UI manusia + MPPS. Keduanya menulis ke RIS (satu DB), jadi tidak ada race antar-service; race hanya mungkin antar-user, ditangani transaksi + validasi status transisi di RIS API.

### 3. Otorisasi lintas produk → X-API-Key + session cookie

- **Machine-to-machine (OMC, AI, Integration, PACS gateway):** `X-API-Key` per service, dari `.env` (pola Integration MS12). OMC & AI wajib punya middleware ini sebelum produksi.
- **Human (RIS, Viewer):** session cookie Laravel (sudah ada). SSO RIS↔Viewer via **shared session + URL token study** (token dibatasi StudyInstanceUID, expire pendek) — bukan shared cookie penuh (simplest secure). Keputusan lanjut dibuka di ADR Viewer bila kompleksitas naik.
- **AI overlay/thumbnail:** endpoint `/inference` AI wajib `X-API-Key`; pemanggilan dari Viewer memakai service key (server-side), bukan token browser pasien.

## Dependensi Lintas Produk (release train ≠ versi per-produk)

Versi per PRD adalah identitas internal produk, **bukan urutan pengerjaan**. Urutan riil ditentukan tabel ini:

| Fitur | Depend pada | Kapan dibutuhkan |
|---|---|---|
| MWL SCP (:4243) | Integration | OMC V2 (worklist) — Integration MVP sudah ada |
| MPPS SCP (:4244) | Integration | OMC V2 (MPPS outbound) — Integration MVP sudah ada |
| C-STORE ke Orthanc | dicom-core + Orthanc | OMC MVP (sudah jalan) |
| Study launch ke OHIF | RIS `study_instance_uid` + OHIF | RIS R6 (sudah jalan) |
| SATUSEHAT adapter | Integration (FHIR) | Wajib kepatuhan — jadwal: sebelum go-live RS |
| AI overlay di Viewer | AI Platform + Viewer V2 | V3 roadmap |

Aturan: **tidak ada produk menunggu versi >-nya sendiri**; pekerjaan mengikuti baris dependensi, bukan angka versi.

## Konsekuensi

- Update `docs/architecture/ARCHITECTURE.md` (diagram + source of truth + alur).
- OMC & AI: tambah `X-API-Key` middleware (backlog — wajib sebelum produksi).
- RIS API: validasi transisi status agar MPPS & UI tidak menulis status invalid.
