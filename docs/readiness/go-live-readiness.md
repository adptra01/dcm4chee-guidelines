# Matriks Readiness Go-Live — ORP untuk RS

Checklist kesiapan deployment produksi, dipetakan ke PRD. Tujuan: terlihat jelas mana yang sudah siap vs blocker sebelum masuk RS riil. Gunakan bersama ADR-006 & backlog.

Legenda: ✅ siap/ada · 🟡 parsial/pending · ⛔ belum · ➖ tidak wajib untuk scope ini.

## A. Regulasi & Kepatuhan

| Item | Ref | Status | Catatan |
|---|---|---|---|
| Koneksi SATUSEHAT | prd-integration Regulasi | 🟡 | Adapter jadi requirement (naik dari V3); belum implement |
| NIK di skema pasien | prd-ris | 🟡 | `patients.patient_id` bisa simpan MRN/NIK; validasi NIK belum |
| FHIR tahap 1 (Patient, Encounter, Condition, dsb) | prd-integration FR-5 | 🟡 | Patient ada; Encounter/Condition/Observation belum |
| Klasifikasi SaMD / izin edar alkes | — | ➖ | Di luar kompetensi dev — konsultasi legal RS/BPOM sebelum produksi |

## B. Keamanan

| Item | State | Status | Catatan |
|---|---|---|---|
| Kredensial via `.env`, no hardcode | SECURITY.md | ✅ | Semua produk ikut |
| Auth API (X-API-Key) | ADR-006 | 🟡 | Integration ✅; OMC, AI ⛔ (backlog wajib) |
| SSO RIS↔Viewer | ADR-006-3 | 🟡 | Keputusan diambil; belum implement token study |
| TLS in-transit | — | ⛔ | Antarmuka internal polos; wajib sebelum produksi |
| Audit trail (siapa akses/ubah data) | prd-ris V2 | ⛔ | Permenkes 24/2022 kewajiban dasar — naik prioritas |
| RBAC (radiograf/radiolog/admin/integrator) | prd-ris V2 | ⛔ | Belum; naik prioritas utk RS |

---

## C. Backup / DR

| Item | State | Status | Catatan |
|---|---|---|---|
| Backup otomatis PACS | prd-pacs US-PACS-001 | ✅ | systemd timer daily, teruji |
| Restore teruji | prd-pacs P3 | ✅ | 2 studi utuh |
| RTO/RPO PACS | prd-pacs Success | 🟡 | restore <15 menit ada; RPO harian |
| RTO/RPO RIS (order & laporan) | prd-ris | ⛔ | Belum ditarget; data klinis kritis perlu RPO/RTO |
| Graceful degradation antar modul (Integration down → OMC tetap C-STORE?) | prd-pacs/ADR-006 | 🟡 | Perlu didefinisikan |
| SOP downtime manual | — | ⛔ | Form kertas cadangan, prosedur rekonsiliasi |

## D. Operasional & Training

| Item | State | Status | Catatan |
|---|---|---|---|
| Strategi rollout (shadow → pilot → bertahap) | backlog | 🟡 | Urutan ada; shadow/pilot belum |
| Exit criteria per tahap rollout | backlog Success Metrics | 🟡 | Success metrics dijadikan syarat go/no-go |
| Materi training end-user (petugas/radiograf/radiolog) | prd-developer-platform | 🟡 | Fokus developer; end-user belum |
| Latihan champion user | — | ⛔ | Belum |
| Jalur feedback minggu pertama | — | ⛔ | Belum |

## Peta Blocker (verbose)

### Blocker sebelum go-live (wajib)
1. TLS in-transit (A. semua komunikasi antar produk)
2. Auth API OMC & AI (ADR-006)
3. Audit trail + RBAC RIS
4. RTO/RPO RIS + graceful degradation
5. SOP downtime + prosedur rekonsiliasi

### Prioritas 2 (kepatuhan opsional-tapi-segera)
- SATUSEHAT adapter + NIK validation + FHIR tahap 1
- SSO token study
- Materi training end-user + champion

### Tidak diperlukan untuk scope (konsultasi regulasi)
- Sertifikasi SaMed/izin alkes — konsultasi legal & BPOM, bukan keputusan dev

---

## Cara pakai

- Update status bar saat menyelesaikan item (kotak).
- Untuk setiap mewajibkan go/no-go tahap rollout, isi "exit criteria terukur" di sel Status.
- Susun sebagai lampiran bab terkait skripsi/jurnal bila target implementasi RS riil.