# Perbandingan: Dokumentasi Teori DCM4CHEE vs Implementasi Sistem

> **Konteks:** Riset/skripsi dengan data simulasi (bukan data pasien riil).
> Tabel ini memisahkan tiga kategori: penyimpangan berisiko, penyederhanaan arsitektur sah,
> dan perbedaan detail implementasi — masing-masing dengan justifikasi desain dan implikasi
> untuk validitas penelitian.

---

## 1. Penyimpangan Berisiko (Perlu Dicatat sebagai Keterbatasan)

| Aspek | Dokumen | Implementasi | Justifikasi Desain | Implikasi Validitas Penelitian |
|-------|---------|--------------|--------------------|--------------------------------|
| **TLS DICOM** | BCP 195 mewajibkan TLS 1.2+ untuk komunikasi DICOM antar-node | Koneksi DICOM plain-text (no TLS) | Data simulasi/sintetis — tidak ada PHI riil. TLS menambah kompleksitas konfigurasi dan troubleshooting tanpa manfaat untuk tujuan pengujian fungsional | **Batasan eksplisit:** Sistem hanya teruji pada jaringan lokal/terisolasi. Untuk deployment data riil, TLS wajib ditambahkan. Tidak mempengaruhi validitas pengujian alur DICOM (C-STORE, C-FIND, MWL) karena enkripsi tidak mengubah logika bisnis |
| **H2 fallback** | WildFly fallback ke H2 embedded jika PostgreSQL tidak tersedia | Belum diverifikasi apakah fallback aktif atau non-aktif | Tidak ada keputusan sadar — belum sempat diuji | **Risiko validitas data uji:** Jika PostgreSQL turun dan fallback ke H2, data baru masuk ke storage terpisah dan hilang saat container restart. Semua data uji setelah titik itu tidak konsisten. **Saran:** Cek log WildFly untuk baris datasource aktif setiap restart, sebelum mengklaim "semua data tersimpan di PostgreSQL". Nonaktifkan H2 fallback untuk produksi |
| **ELK logging terpusat** | Stack ELK untuk monitoring operasional | Tidak ada logging terpusat | Di luar scope penelitian — fokus pada fungsionalitas inti PACS + portal | **Batasan:** Tidak ada audit trail "siapa akses studi pasien X kapan". Untuk skripsi, opsional kecuali observability adalah fokus penelitian. Cukup disebut sebagai future work |

## 2. Penyederhanaan Arsitektur Sah (Bukan Masalah)

| Aspek | Dokumen | Implementasi | Justifikasi Desain | Implikasi Validitas Penelitian |
|-------|---------|--------------|--------------------|--------------------------------|
| **Service UI terpisah** | Container `ui` terpisah dengan `WILDFLY_DEPLOY_UI=only` | UI menyatu di container `arc` | Container `arc` sudah mencakup WAR UI — tidak perlu container tambahan untuk fungsionalitas yang sama. Satu service lebih sederhana untuk deployment riset | Tidak ada implikasi — fungsionalitas UI identik. Pembaca perlu diberi tahu bahwa ini penyederhanaan deployment, bukan perubahan arsitektur fungsional |
| **Single instance (no cluster)** | Multi-instance arc untuk HA/scalability | Single instance arc | Skala riset tidak memerlukan high-availability. Clustering akan jadi over-engineering tanpa kebutuhan beban nyata | **Batasan eksplisit:** Sistem belum teruji pada beban produksi RS riil (ratusan studi/hari). Generalisasi ke performa produksi tidak valid tanpa pengujian beban lanjutan |
| **MariaDB untuk Keycloak** | Tidak disebut (implisit: PostgreSQL untuk semua) | Keycloak pakai MariaDB terpisah dari PostgreSQL PACS | Best practice standar Keycloak — pisahkan auth DB dari application DB. MariaDB dipilih karena image Keycloak resmi mendukungnya | Tidak ada implikasi negatif. Justru menunjukkan pemahaman arsitektur keamanan yang baik |

## 3. Perbedaan Detail Implementasi

| Aspek | Dokumen | Implementasi | Justifikasi Desain | Implikasi Validitas Penelitian |
|-------|---------|--------------|--------------------|--------------------------------|
| **Alur order** | HL7 v2/MLLP dari HIS/RIS ke DCM4CHEE | Filament Registration → REST MWL-RS → PACS | REST API adalah arsitektur modern yang didorong IHE lewat profil mWL. Portal Laravel sudah REST-native — tidak perlu middleware HL7 tambahan untuk komunikasi internal | **Batasan untuk interoperabilitas:** Jika penelitian mengklaim sistem siap integrasi RIS/HIS eksternal, perlu disebut bahwa hanya sistem REST-native yang didukung. Interoperabilitas dengan RIS/HIS legacy (bicara HL7) memerlukan adapter tambahan |
| **Akses studi** | Weasis web viewer | Filament StudyBrowser (REST QIDO-RS) + PACS Web UI langsung | StudyBrowser adalah custom page untuk membaca metadata studi dari PACS via REST. Viewing DICOM asli tetap bisa dilakukan lewat PACS Web UI | Perlu disebut bahwa viewing DICOM pixel data via portal sendiri belum diimplementasi. Untuk riset, metadata sudah cukup untuk demonstrasi fungsionalitas portal |
| **Peralatan (sumber gambar)** | Modalitas sungguhan (CT/MRI/X-ray) | Python pynetdicom simulator + SCP server port 11114 | Simulator cukup untuk menguji alur DICOM secara fungsional tanpa needing hardware modalitas sungguhan | **Batasan metodologis paling penting:** Simulator pynetdicom bagus untuk menguji alur DICOM fungsional (C-STORE, C-FIND, MWL) tapi tidak bisa memvalidasi: variasi conformance statement antar-vendor, volume data riil (ukuran file CT/MRI asli bisa 100-500MB per studi), timing/latency jaringan realistis. Jika penelitian mengklaim "siap pakai di RS", gap ini harus diakui sebagai batasan generalisasi hasil |
| **Transfer Capabilities** | Didefinisikan per modalitas di LDAP | Default konfigurasi image | Untuk pengujian fungsional dengan simulator, default sudah mencukupi | Tidak ada implikasi untuk riset selama data uji menggunakan transfer syntax standar (Explicit VR Little Endian, JPEG Baseline) |
| **3 varian docker-compose** | Single varian deployment | base (localhost), public (192.168.2.220), private (192.168.2.220) | Kebutuhan deployment berbeda: lokal untuk development, public untuk akses eksternal, private untuk internal network | Menunjukkan fleksibilitas arsitektur. Perlu dokumentasi jelas tentang varian mana yang dipakai untuk setiap skenario pengujian di bab metode |
| **AUTH_SERVER_URL / WILDFLY_WAIT_FOR** | Tidak disebut di dokumen teori | Wajib diset di environment | Environment variable spesifik image dcm4chee — diperlukan untuk integrasi Keycloak dan orchestrasi container startup order | Ini adalah detail implementasi yang wajar tidak tercakup di dokumen teori generik. Tidak ada implikasi untuk validitas |

---

## Ringkasan untuk Skripsi

### Yang wajib ditulis di **Bab Keterbatasan (Limitations)**:
1. **Data simulasi** — hasil hanya valid untuk lingkungan terisolasi, bukan beban produksi RS riil
2. **Tidak ada TLS DICOM** — untuk data riil, enkripsi wajib ditambahkan
3. **Simulator vs modalitas sungguhan** — variasi vendor DICOM dan volume data riil tidak teruji
4. **Tidak ada integrasi HL7** — interoperabilitas dengan RIS/HIS legacy terbatas

### Yang wajib ditulis di **Bab Saran (Future Work)**:
1. Verifikasi status H2 fallback dan nonaktifkan untuk produksi
2. Implementasi logging terpusat (ELK) untuk audit trail
3. Pengujian performa dengan data volume produksi
4. Integrasi web viewer DICOM (Weasis atau OHIF) untuk viewing langsung di portal

### Yang perlu disebut di **Bab Metode** sebagai justifikasi desain:
- REST API sebagai pengganti HL7 — alasan: arsitektur Laravel/Filament yang REST-native
- Single instance — alasan: skala riset, hindari over-engineering
- Simulator pynetdicom — alasan: pengujian fungsional tanpa hardware modalitas

---

> **Dibuat:** 2026-07-11
> **Tujuan:** Dokumentasi perbandingan untuk bahan bab pembahasan dan keterbatasan skripsi
