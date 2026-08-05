# ADR-005 — Strategi Docker Compose: Root + Per Produk

- Status: **Accepted** (2026-08-05)
- Konteks: perlu menjalankan seluruh platform sekaligus, TAPI kontributor juga
  butuh menjalankan satu produk saja (mis. hanya AI) tanpa seluruh stack.

## Keputusan

Dua level compose, bukan satu:

1. **Root `docker-compose.yml`** — seluruh stack dev:
   `db`, `orthanc`, `ohif`, `redis`, `omc-api`, `omc-console`, `ai-service`,
   `integration-service`. Perintah: `docker compose up`.
2. **Compose per produk** — tiap aplikasi punya `compose.yml` sendiri di
   foldernya, agar bisa jalan standalone:
   `cd products/omc/api && docker compose up`.
3. **RIS khusus DDEV** — Laravel dijalankan via `ddev start` (keputusan
   terkunci: DDEV hanya untuk RIS), bukan compose.

## Alasan

- Developer fokus AI cukup `cd products/ai/api && docker compose up`, tanpa
  OHIF/Orthanc/RIS.
- Root compose tetap menjamin "satu perintah, semua hidup" untuk kontributor
  yang ingin menjalankan seluruh platform.

## Konsekuensi

- Dua sumber kebenaran compose (root + per-produk) — harus dijaga sinkron.
- Variabel umum (`ports`, `env`) di `.env` root; compose per-produk memakai
  default yang aman bila `.env` tidak ada.

## Lihat juga

- ADR-002 (monorepo), ADR-001 (arsitektur)
