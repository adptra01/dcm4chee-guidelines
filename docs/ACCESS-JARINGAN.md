# Akses PACS Lintas Jaringan

Dokumen ini menjelaskan cara workstation PZDR (dan viewer) mengakses PACS Orthanc dari
berbagai posisi jaringan. Diterapkan setelah stack berjalan.

---

## Info Host saat ini

| Item | Nilai |
|---|---|
| Hostname | `cachyos-x8664` |
| IP utama | `10.205.136.1` (via **WiFi / wlan0**) |
| Subnet | `10.205.136.0/24` |
| Gateway / DHCP | `10.205.136.141` |
| Port listener | `4242` (DICOM), `8042` (REST), `3000` (OHIF) — semua bind `0.0.0.0` |

> Port sudah `0.0.0.0` → **sudah bisa diakses dari mesin lain** dalam subnet yang sama,
> tanpa perubahan konfigurasi. Tugasnya hanya: pastikan workstation tahu IP yang benar,
> dan firewall tidak memblokir.

---

## Skenario & Langkah

### Skenario A — PZDR satu subnet dengan host (paling umum)

PZDR dan host Docker di jaringan yang sama (mis. keduanya `10.205.136.x`).

1. Gunakan IP host langsung: **`10.205.136.1`** sebagai Hostname di PZDR (4.6.4) dan di OHIF.
2. Tes dari workstation:
   ```powershell
   ping 10.205.136.1
   Test-NetConnection 10.205.136.1 -Port 4242   # harus True
   ```
3. **OHIF viewer** dibuka dari browser mesin lain → `ohif/app-config.js` harus memakai
   IP host, bukan `localhost`. Ubah file lalu restart:

```javascript
// ohif/app-config.js — ganti ketiga URL
wadoUriRoot: 'http://10.205.136.1:8042/wado',
qidoRoot:   'http://10.205.136.1:8042/dicom-web',
wadoRoot:   'http://10.205.136.1:8042/dicom-web',
```
```bash
docker compose restart ohif
```

---

### Skenario B — Host pindah subnet / IP berubah (DHCP)

IP host dari DHCP (`10.205.136.1`) **bisa berubah**. Kalau IP berubah, PZDR & OHIF putus.

**Solusi permanen: pin IP statis di host** (anggota DHCP reservation / manual):

```bash
# cek config networkmanager service yang dipakai wifi
nmcli -t -f NAME,DEVICE,TYPE con show | grep wlan0
# lalu set ipv4 manual contoh:
nmcli con mod "<nama-connection>" ipv4.method manual \
  ipv4.addresses 10.205.136.1/24 ipv4.gateway 10.205.136.141 \
  ipv4.dns "8.8.8.8 8.8.4.4"
nmcli con up "<nama-connection>"
```

Setelah IP statis, nilai `Hostname` di PZDR dan `.env` (`REST/OHIF`) stabil.

---

### Skenario C — PZDR di VLAN/subnet berbeda (routing aktif)

Jaringan RS biasanya memisahkan VLAN. Kondisi:
- host Docker di VLAN A (`10.205.136.x`), PZDR di VLAN B (`192.168.2.x`)
- Router antar-VLAN sudah meneruskan traffic DICOM.

1. Pastikan port `4242` dibuka di **firewall host** untuk subnet PZDR:
   ```bash
   sudo firewall-cmd --permanent --add-service=dicom --add-port=4242/tcp \
     && sudo firewall-cmd --reload
   # atau bila pakai ufw:
   sudo ufw allow from 192.168.2.0/24 to any port 4242 proto tcp
   sudo ufw allow from 192.168.2.0/24 to any port 8042 proto tcp
   sudo ufw allow from 192.168.2.0/24 to any port 3000 proto tcp
   ```
2. Di PZDR Hostname pakai IP host (= gateway yang bisa tembus ke VLAN B).
3. Kalau VLAN B **tidak punya rute balik** ke VLAN A → perlu NAT/port-forward di router,
   atau tunnel (lihat Skenario D).

---

### Skenario D — Lokasi terpisah (RS ≠ lab) → Tunnel

Kalau PZDR berada di lokasi yang tidak bisa menjangkau host Docker langsung (berbeda
jaringan fisik / internet), gunakan **VPN** atau **SSH tunnel**. Ini yang dipakai untuk
remote PACS `103.147.236.138` (dari catatan Anda), karena port diblock di MikroTik.

**SSH tunnel dari host Docker → forward port DICOM/REST lokal:**

```bash
# di host Docker, buat tunnel agar port PACS diakses dari jaringan lokal:
ssh -N -L 0.0.0.0:11112:localhost:4242 user@server-jauh
```

- PZDR Hostname = IP host lokal, Port = `11112` (dipetakan ke 4242 via tunnel).
- Keterbatasan: PZDR tidak punya retry cerdas melewati tunnel; pastikan tunnel stabil
  (systemd unit + auto-restart).

**Alternatif — Tailscale/WireGuard VPN:**
```bash
# contoh WireGuard di host Docker, lalu akses via IP VPN host
sudo pacman -S wireguard-tools   # CachyOS
```

---

## Ringkasan Keputusan yang Perlu Diambil

| Pertanyaan | Untuk skenario |
|---|---|
| PZDR dan host satu subnet? | A → langsung pakai `10.205.136.1`, beres |
| IP host bisa berubah (DHCP)? | B → pin statis host |
| PZDR di VLAN lain, routing jalan? | C → buka firewall host untuk subnet PZDR |
| Lokasi berbeda / tidak tembus? | D → VPN/SSH tunnel |

---

## Yang Dipakai Panduan Install (dokumen terkait)

- `docs/INSTALL-PZDR.md` — memakai nilai `10.205.136.1`. **Kalau pakai skenario C/D,
  ganti nilai di langkah Tahap 3** dengan IP yang benar sesuai skenario.
- `ohif/app-config.js` — `localhost` → IP host sesuai skenario A/B agar viewer terbuka
  dari mesin lain.

---

## Rekomendasi Tambahan

> **WiFi bukan ideal untuk PACS.** Host terhubung via `wlan0`. DICOM (DX/CR) datanya
> puluhan MB per studi; WiFi bisa lambat/putus. Untuk produksi, sambungkan host Docker
> via **kabel Ethernet (Pro 1000M)** — opsional sekarang, tetapi disarankan sebelum
> alur produksi aktif.