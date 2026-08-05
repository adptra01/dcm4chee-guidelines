# ORP Integration — Integration Service

Produk Integration: adapter untuk SIMRS/eksternal (MORBIS, FHIR, HL7). Pola
**Adapter** (ADR-004): core tidak berubah saat menambah sistem baru.

## Stack
- **morbis/** — adapter MORBIS/SIMRS lokal
- **fhir/** — adapter FHIR R4
- **hl7/** — adapter HL7 v2

## Batasan (ADR-001)
- Integrasi menghubungkan **Laravel RIS** ⇄ sistem eksternal
- Tidak menyimpan data sendiri — penerjemah kontrak

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | Skeleton, health |
| v0.2 | MORBIS |
| v0.3 | FHIR, HL7 |

## Jalankan
```bash
cd products/integration && docker compose up
```
