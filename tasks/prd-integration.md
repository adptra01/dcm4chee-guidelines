# PRD: ORP Integration Platform

## Introduction

Pusat komunikasi antar produk ORP dan sistem eksternal (SIMRS, BPJS/SATUSEHAT, OpenMRS, OpenEMR). Produk paling penting: semua komunikasi lintas sistem lewat sini. Menyediakan REST, HL7 v2, FHIR R4, webhook, event bus, MWL, MPPS, dan adapter per SIMRS. Interoperabilitas dengan kredensial via `.env` (SECURITY.md) — tanpa hardcode.

## Goals

- Satu pintu komunikasi eksternal (adapter + API key auth)
- Interoperabilitas DICOM: MWL & MPPS SCP untuk modalitas
- Interoperabilitas klinis: HL7 v2 & FHIR R4
- Event outbound (webhook) dengan signature & retry
- Adapter mudah ditambah (MORBIS, SATUSEHAT, OpenMRS, OpenEMR, custom)

## Status Saat Ini (pemetaan ulang dari MS1–13)

Sudah tercover di `products/integration` (16 test):
- MORBIS adapter: SEP + klaim, HMAC signature, mock/real — MS6
- HL7 v2: ADT-A01 → RIS patient, ORM-O01 — MS10
- MWL SCP C-FIND :4243 — MS9
- MPPS SCP N-CREATE/N-SET :4244 → status order — MS13
- FHIR R4: Patient, ServiceRequest, DiagnosticReport, Bundle searchset — MS8
- API key auth (X-API-Key) endpoint eksternal — MS12

Belum tercover: webhook outbound, queue+retry, adapter SATUSEHAT, service discovery, marketplace.

## Regulasi (wajib, bukan opsional)

- **Permenkes 24/2022** Pasal 21 & 24: RME fasilitas kesehatan wajib terhubung SATUSEHAT; pengiriman data rujukan lewat platform tersebut. Integrasi dikejar untuk go-live RS.
- **NIK** = identifier utama SATUSEHAT; skema pasien RIS wajib menampung/validasi NIK (bukan cuma MRN internal).
- **FHIR wajib tahap 1**: Organization, Location, Practitioner/PractitionerRole, Patient, Encounter, Condition, Observation. **Tahap 2**: Procedure, MedicationRequest, ServiceRequest, DiagnosticReport.
- Implikasi: **SATUSEHAT adapter naik dari V3 → requirement resmi sejajar MVP** (lihat US-INT-003 & jadwal go-live). MORBIS tetap prototipe pola adapter → SATUSEHAT turunan (desain sudah tepat).

## Fase

- **MVP (v0.7):** REST, Webhook, MWL
- **V2 (v0.9+):** FHIR, HL7, Queue, Retry
- **V3 (v1.0+):** Integration Marketplace
- **Kepatuhan (go-live RS):** SATUSEHAT adapter — dijadwalkan, bukan menunggu V3

## User Stories

### US-INT-001: Webhook outbound event
**Description:** Sebagai integrator, saya ingin ORP mengirim notifikasi ke URL eksternal saat event terjadi (order dibuat, report final) agar SIMRS sinkron real-time.

**Acceptance Criteria:**
- [ ] Event: order.created, order.status_changed, report.final, study.sent
- [ ] Webhook config per endpoint (URL, event dipilih, secret, aktif/nonaktif)
- [ ] Payload JSON dengan signature HMAC (pola MORBIS)
- [ ] Log pengiriman sukses/gagal terlihat
- [ ] Retry otomatis untuk gagal (V2, via queue)
- [ ] Test event → webhook lulus

### US-INT-002: Message queue + retry (V2)
**Description:** Sebagai integrator, saya ingin pesan antar-layanan tidak hilang saat target down agar komunikasi andal.

**Acceptance Criteria:**
- [ ] Antrean pesan persisten (SQLite minimal, Redis jika ada)
- [ ] Retry backoff (mis. 1s/10s/60s) untuk gagal
- [ ] Dead-letter: pesan yang menyerah tercatat, tidak dihapus
- [ ] Test queue + retry + dead-letter lulus

### US-INT-003: Adapter SATUSEHAT (V3)
**Description:** Sebagai integrator, saya ingin adapter SATUSEHAT agar interoperable dengan ekosistem kesehatan nasional.

**Acceptance Criteria:**
- [ ] Adapter SATUSEHAT (FHIR + token OAuth)
- [ ] Kredensial via env, mock mode default (pola MORBIS)
- [ ] Test mock adapter lulus

## Functional Requirements

- FR-1: API key auth (X-API-Key) untuk semua endpoint eksternal — jangan regresi
- FR-2: MWL SCP C-FIND pada :4243 (AE MWL_SCP) — jangan regresi
- FR-3: MPPS SCP N-CREATE/N-SET :4244 (AE MPPS_SCP) → update order status — jangan regresi
- FR-4: HL7 v2 ADT-A01/ORM-O01 — jangan regresi
- FR-5: FHIR R4 Patient/ServiceRequest/DiagnosticReport, Content-Type `application/fhir+json` — jangan regresi
- FR-6: Webhook outbound dengan HMAC signature + log
- FR-7: Adapter interface seragam (adapter protocol) sehingga menambah SIMRS baru = implement 1 interface
- FR-8: Semua kredensial via `.env`, tidak pernah hardcode (SECURITY.md)

## Non-Goals

- Tidak menyimpan data pasien klinis (RIS pemilik)
- Tidak menulis DICOM store/retrieve (PACS/Orthanc)
- Tidak ada UI administrasi penuh di MVP (cukup config + log)
- Tidak ada transformasi data kompleks di MVP (ETL)

## Design Considerations

- Pola adapter seragam: MORBIS = prototipe; SATUSEHAT = turunan
- Webhook payload mengikuti pola signature MORBIS (HMAC + timestamp) — konsisten
- Log webhook di file/DB, bukan stdout saja

## Technical Considerations

- Python (FastAPI), pynetdicom untuk SCP
- Ports: MWL 4243, MPPS 4244 — dikunci; jangan ubah tanpa ADR
- Mock default untuk semua adapter eksternal; `*_MODE=real` + kredensial untuk produksi
- Queue: mulai SQLite (seperti OMC queue_store), naik Redis kalau throughput butuh

## Success Metrics

- 16 test integration → target 24+ setelah webhook & queue
- Webhook delivered rate > 99% setelah retry (dev)
- Adapter baru (SATUSEHAT) selesai < 2 hari kerja (bukti interface reusable)

## Open Questions

- Event bus: pub/sub (Redis) atau cukup webhook outbound?
- Queue: SQLite dulu cukup, atau Redis langsung?
- SATUSEHAT: FHIR resource mana yang diprioritaskan (patient, encounter, observation)?
