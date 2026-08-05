# ORP RIS — Radiology Information System

Produk RIS: data klinis & workflow bisnis rumah sakit. **Source of truth** untuk
Patient, Order, Report, Audit (ADR-001).

## Stack
- **backend/** — Laravel (PHP ^8.2), dijalankan via **DDEV** (keputusan terkunci: DDEV hanya untuk RIS)
- **frontend/** — Livewire/Filament (bawaan Laravel)
- **docs/** — roadmap & dokumentasi produk

## Batasan (ADR-001)
- ❌ Laravel **tidak pernah** berbicara DICOM (tidak ada C-STORE/C-ECHO/association)
- ✅ Hanya REST + JWT + database
- Semua komunikasi DICOM lewat OMC / Orthanc

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | Patient, Order, Login |
| v0.2 | Worklist, Report |
| v0.3 | Scheduling |

## Jalankan
```bash
cd products/ris && ddev start
```
