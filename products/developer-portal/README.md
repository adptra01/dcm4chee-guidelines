# ORP Developer Portal

Portal pengembang: dokumentasi API (OpenAPI/Swagger), panduan membuat adapter,
arsitektur & ADR, dataset contoh, panduan kontribusi, cara menjalankan seluruh
stack secara lokal.

Target pembaca: **kontributor**, bukan pengguna rumah sakit.

## Isi (rencana)
- OpenAPI/Swagger seluruh produk + contoh penggunaan
- Panduan adapter baru (SIMRS, modality, PACS)
- Arsitektur & ADR
- Panduan menjalankan seluruh stack lokal
- Coding standards, branching, testing

## Roadmap
| Versi | Fitur |
|---|---|
| v0.1 | Skeleton bootable (Vitepress/Docusaurus) |
| v0.2 | OpenAPI aggregation |
| v0.3 | Panduan kontribusi lengkap |

## Jalankan
```bash
cd products/developer-portal && npm install && npm run dev
```
