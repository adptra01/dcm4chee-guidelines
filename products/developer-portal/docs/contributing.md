# Panduan Kontribusi

## Struktur

- `products/` — domain bisnis (ris, omc, ai, viewer, integration)
- `packages/` — library reusable. **Tidak boleh** depend ke `products/` (ADR-002)
- `platform/` — infrastruktur docker

## Menjalankan seluruh stack

```bash
cp .env.example .env
docker compose up -d        # platform: orthanc, postgres, ohif
./scripts/check.sh          # verifikasi
```

### Per produk

| Produk | Perintah |
|---|---|
| RIS | `cd products/ris/backend && ddev start` |
| OMC API | `cd products/omc/api && docker compose up` |
| OMC Console | `cd products/omc/console && npm install && npm run dev` |
| AI / Integration | `cd products/<x> && docker compose up` |

## Aturan

1. **Core Rule** (ADR-003): jangan simpan DICOM di luar Orthanc.
2. **Dependency**: `packages/*` tidak boleh import dari `products/*`.
3. **Bootable**: setiap perubahan harus menjaga produk tetap bisa dijalankan.
4. **Test**: service FastAPI wajib punya test (minimal `/health`).

## Milestone

Lihat `README.md` root — MS0 (bootstrap), MS1 (dicom-core), MS2 (OMC API slice), dst.
