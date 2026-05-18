# DCM4CHEE Implementation Training
## Complete 3-Level Course for Healthcare IT Professionals

---

## Course Overview

**Judul:** Implementasi DCM4CHEE Archive 5.x untuk Sistem PACS/Rumah Sakit

**Tujuan:** Membimbing praktisi Healthcare IT Indonesia dari nol hingga mampu mengimplementasikan DCM4CHEE Archive 5.x di lingkungan rumah sakit production-ready, mulai dari RS kecil hingga RS rujukan.

**Target Peserta:**
- IT Staff rumah sakit (RS kecil-menengah)
- System Administrator dengan pengalaman Linux
- Healthcare IT Consultant
- Radiology IT Specialist
- Database Administrator (DBA)

**Prasyarat Umum:**
- Pemahaman dasar networking (TCP/IP, ports)
- Familiar dengan command line Linux
- Pemahaman dasar database (PostgreSQL/MySQL)
- Pengalaman dengan Docker (opsional tapi sangat membantu)

---

## Learning Path

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    DCM4CHEE TRAINING PATH                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  LEVEL 1: BASIC                                                         │
│  ├── Fondasi teori DICOM & healthcare IT                                │
│  ├── Arsitektur DCM4CHEE                                                │
│  ├── Instalasi Docker Compose                                          │
│  ├── Hands-on: C-ECHO, STOW-RS, QIDO-RS, AE Configuration              │
│  └── Studi kasus: RS Kecil (1-2 modality)                               │
│       │                                                                  │
│       ▼                                                                  │
│  LEVEL 2: INTERMEDIATE                                                  │
│  ├── Konfigurasi PACS parameter sets                                    │
│  ├── Modality Worklist (MWL) integration                              │
│  ├── HIS/EMR integration (HL7 ORM/ADT)                               │
│  ├── QR SCP & REST API configuration                                   │
│  ├── PostgreSQL optimization                                           │
│  ├── Hands-on: Multi-modality, MWL, OHIF, DB benchmark                │
│  └── Studi kasus: RS Menengah (5-8 modalities, HIS integration)        │
│       │                                                                  │
│       ▼                                                                  │
│  LEVEL 3: ADVANCED                                                      │
│  ├── Security: TLS, Keycloak SSO                                       │
│  ├── Backup & Disaster Recovery                                        │
│  ├── High Availability (Active-Passive, clustering)                   │
│  ├── Performance tuning (JVM, DB, I/O, network)                       │
│  ├── Elastic Stack monitoring                                           │
│  ├── Compliance & audit trail                                           │
│  ├── PACS migration strategy                                           │
│  └── Studi kasus: RS Rujukan (10+ modalities, 200+ beds)               │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Level 1: Basic - Fondasi

**File:** `LEVEL-1-BASIC.md`

**Durasi estimasi:** 2-3 hari

### Modul yang Dicakup

| Modul | Topik | Waktu |
|-------|-------|-------|
| 1 | Konsep Dasar DICOM (model data, services, SOP classes, AE title) | 3 jam |
| 2 | Arsitektur DCM4CHEE (WildFly, LDAP, PostgreSQL, storage) | 2 jam |
| 3 | Instalasi Docker Compose (step-by-step) | 3 jam |
| 4 | Hands-On Exercises (4 exercises) | 4 jam |
| 5 | Arsitektur jaringan & DICOM message flow | 2 jam |
| 6 | Studi kasus: RS Kecil (1-2 modality) | 2 jam |
| 7 | Troubleshooting & Best Practices | 2 jam |

**Total:** ~20 jam

### Tujuan Pembelajaran (Learning Objectives)

1. **Memahami** konsep dasar DICOM dan perannya dalam healthcare IT
2. **Menjelaskan** arsitektur komponen DCM4CHEE Archive
3. **Menginstall** DCM4CHEE via Docker Compose
4. **Melakukan** verifikasi konektivitas DICOM dasar
5. **Mengintegrasikan** test modality/client ke archive
6. **Men-deploy** di RS kecil dengan 1-2 modality

### Hands-On Exercises

| # | Exercise | Tools | Expected Result |
|---|----------|-------|----------------|
| 1 | C-ECHO Verification | echoscu / DICOM viewer | Association accepted |
| 2 | STOW-RS Upload | curl + sample DICOM | Study created |
| 3 | QIDO-RS Query | curl | JSON metadata response |
| 4 | AE Title Configuration | Web UI | Modality bisa connect |

### Studi Kasus: RS Sehat (RS type A - 50 beds, 2 modality)

- 1× CR Digital X-Ray
- 1× USG
- 50 bed capacity
- 1 radiologist
- IT staff: 1 orang

### Success Criteria

- [ ] DCM4CHEE accessible via Web UI
- [ ] C-ECHO test berhasil
- [ ] Modality bisa kirim gambar (C-STORE)
- [ ] QIDO-RS query mengembalikan data
- [ ] Storage configured dengan proper permissions
- [ ] Backup script created

---

## Level 2: Intermediate - Integrasi

**File:** `LEVEL-2-INTERMEDIATE.md`

**Durasi estimasi:** 3-5 hari

### Modul yang Dicakup

| Modul | Topik | Waktu |
|-------|-------|-------|
| 1 | Konfigurasi PACS Parameter Sets (AE, storage, device config) | 3 jam |
| 2 | Modality Worklist (MWL) Integration (HL7 ORM) | 4 jam |
| 3 | HIS/EMR Integration (SIMRS, Bahmni - RS Indonesia) | 4 jam |
| 4 | Query/Retrieve SCP Configuration (C-MOVE/C-GET/QIDO-RS) | 3 jam |
| 5 | Database PostgreSQL Optimization | 4 jam |
| 6 | Hands-On Exercises (4 exercises) | 6 jam |
| 7 | Studi kasus: RS wijayakusuma (RS type B - 150 beds) | 3 jam |
| 8 | Troubleshooting Intermediate & Best Practices | 2 jam |

**Total:** ~30 jam

### Prerequisites

- Level 1 completed
- DCM4CHEE running production-ready di lab/server
- 2+ modalities mau dikonfigurasi
- Sistem HIS/RIS tersedia untuk integration test

### Tujuan Pembelajaran

1. **Mengkonfigurasi** multi-modality AE titles
2. **Mengimplementasikan** MWL dari HL7 ORM messages
3. **Mengintegrasikan** DCM4CHEE dengan HIS/EMR RS
4. **Mengoptimasi** PostgreSQL untuk production load
5. **Men-setup** Query/Retrieve SCP untuk berbagai DICOM clients
6. **Mengintegrasikan** web viewer (OHIF) dengan WADO-RS

### Hands-On Exercises

| # | Exercise | Tools | Expected Result |
|---|----------|-------|----------------|
| 1 | Multi-Modality AE Configuration | Web UI | 3 modality bisa C-ECHO |
| 2 | MWL Setup dari HL7 Orders | HL7 simulator | MWL entry created |
| 3 | WADO-RS untuk OHIF Viewer | OHIF + curl | Image displayed in viewer |
| 4 | DB Performance Benchmark | psql + EXPLAIN ANALYZE | Query < 1 detik |

### Studi Kasus: RS Wijayakusuma (RS type B - 150 beds)

- 3× CT, 2× MRI, 2× CR, 2× USG, 1× XA
- 150 beds
- Integrasi dengan SIMRS Ganesha
- 3 radiologist, 8 technologist
- IT staff: 2 orang

### Success Criteria

- [ ] 3+ modalities terhubung dan bisa kirim gambar
- [ ] MWL entries terbuat dari HL7 ORM messages
- [ ] HIS (SIMRS) bisa kirim order ke DCM4CHEE
- [ ] Modality bisa retrieve MWL worklist
- [ ] PostgreSQL query performance < 1 detik
- [ ] OHIF viewer bisa load study dari archive

---

## Level 3: Advanced - Production-Ready

**File:** `LEVEL-3-ADVANCED.md`

**Durasi estimasi:** 5-7 hari

### Modul yang Dicakup

| Modul | Topik | Waktu |
|-------|-------|-------|
| 1 | Security: TLS/SSL, Keycloak SSO, DICOM TLS | 5 jam |
| 2 | Backup & Disaster Recovery (3-2-1 strategy) | 5 jam |
| 3 | High Availability (Active-Passive, HAProxy, clustering) | 6 jam |
| 4 | Performance Tuning Lanjutan (JVM, DB, I/O, network) | 4 jam |
| 5 | Elastic Stack Monitoring (Elasticsearch, Logstash, Kibana) | 4 jam |
| 6 | Compliance & Audit (IHE ATNA, HIPAA considerations) | 3 jam |
| 7 | Migration dari PACS Lama | 4 jam |
| 8 | Studi kasus: RSUP Hasanuddin (RS type A - 400 beds) | 4 jam |

**Total:** ~35 jam

### Prerequisites

- Level 1-2 completed
- DCM4CHEE sudah running di production/test environment
- Infrastructure untuk HA (second server, shared storage)
- Budget untuk security certificates dan monitoring tools

### Tujuan Pembelajaran

1. **Mengimplementasikan** TLS/SSL untuk semua communications
2. **Men-setup** Keycloak SSO dengan role-based access
3. **Merancang** backup strategy 3-2-1 dengan disaster recovery plan
4. **Mengimplementasikan** HA cluster dengan failover
5. **Mengoptimasi** performance untuk 500+ study/hari
6. **Men-setup** advanced monitoring dengan Elastic Stack
7. **Memahami** compliance requirements (audit trail, data retention)
8. **Melakukan** migrasi dari PACS lama ke DCM4CHEE

### Hands-On Exercises

| # | Exercise | Tools | Expected Result |
|---|----------|-------|----------------|
| 1 | Keycloak Secured Deployment | docker-compose-secure | User auth via Keycloak |
| 2 | PostgreSQL Streaming Replication | pg_basebackup | DB replication active |
| 3 | Keepalived Failover Setup | keepalived + health script | Auto-failover working |
| 4 | ELK Stack Integration | Elasticsearch + Logstash | Logs aggregated in Kibana |

### Studi Kasus: RSUP Hasanuddin (RS type A - 400 beds)

- 2× CT (256 slice), 2× MRI (3T), 3× CR, 3× USG, 1× XA, 1× Mammo
- 400 beds
- Teaching hospital
- Full HIS integration (SIMRS), RIS, EMR
- 30+ radiologist/resident, 15 technologist
- IT staff: 5 orang + 1 DBA
- SLA: 99.9% uptime, RTO < 1 jam, RPO < 1 jam

### Success Criteria

- [ ] Secured deployment dengan Keycloak SSO
- [ ] TLS aktif untuk Web UI dan REST API
- [ ] Backup automated (DB, storage, LDAP, config)
- [ ] DR procedure documented dan tested
- [ ] HA cluster dengan auto-failover
- [ ] PostgreSQL streaming replication active
- [ ] Kibana dashboard dengan 5+ visualizations
- [ ] Monitoring alert ke Slack/Email
- [ ] Data retention policy configured
- [ ] Migration dari PACS lama completed dan verified

---

## File Structure

```
dcm4chee-training/
├── README.md              # This file - course overview
├── LEVEL-1-BASIC.md       # Level 1 content
├── LEVEL-2-INTERMEDIATE.md # Level 2 content
├── LEVEL-3-ADVANCED.md    # Level 3 content
└── SKILL.md              # DCM4CHEE Docker deployment skill
```

---

## Quick Reference

### Important URLs

| Service | URL | Notes |
|---------|-----|-------|
| Archive Web UI | `http://server:8080/dcm4chee-arc/ui2` | Login: root/changeit |
| WildFly Console | `http://server:9990` | Admin: root/changeit |
| QIDO-RS Base | `http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs` | |
| WADO-RS Base | `http://server:8080/dcm4chee-arc/aets/DCM4CHEE/rs` | |
| WADO-URI | `http://server:8080/dcm4chee-arc/aets/DCM4CHEE/wado` | |
| Keycloak (secured) | `http://server:12575` | Admin console |
| Kibana (secured) | `http://server:5601` | Via OAuth2 proxy |

### Default Credentials

| Service | Username | Password |
|---------|----------|----------|
| Archive Web UI | root | changeit |
| WildFly Console | root | changeit |
| PostgreSQL | pacs | pacs |
| OpenLDAP | cn=admin,dc=dcm4che,dc=org | changeit |
| Keycloak | root | [see .env file] |

### Key Ports

| Port | Protocol | Service |
|------|----------|---------|
| 8080 | HTTP | Archive Web UI & REST API |
| 8443 | HTTPS | Secured Archive |
| 9990 | HTTP | WildFly Admin Console |
| 9993 | HTTPS | Secured WildFly Console |
| 11112 | DICOM | Storage SCP / Query-Retrieve SCP |
| 2762 | HL7 MLLP | HL7 ORM Receiver (orders) |
| 2575 | HL7 MLLP | HL7 ADT Receiver (patient events) |
| 389 | LDAP | OpenLDAP |
| 5432 | PostgreSQL | Database |
| 12575 | HTTP | Keycloak (internal) |
| 8843 | HTTPS | OAuth2 Proxy |
| 9200 | HTTP | Elasticsearch |
| 5601 | HTTP | Kibana |

### Key DICOM AE Titles

| AE Title | Purpose |
|----------|---------|
| DCM4CHEE | Archive utama |
| DCM4CHEE_MWL | Modality Worklist SCP |
| DCM4CHEE_QIDO | QIDO-RS service |
| [modality-specific] | Setiap modality/client |

### Official Resources

| Resource | URL |
|----------|-----|
| GitHub Repository | https://github.com/dcm4che/dcm4chee-arc-light |
| Wiki (Docker) | https://github.com/dcm4che/dcm4chee-arc-light/wiki/Running-on-Docker |
| Installation Guide | https://github.com/dcm4che/dcm4chee-arc-light/wiki/Installation |
| Docker Hub | https://hub.docker.com/u/dcm4che |
| SourceForge Binaries | https://sourceforge.net/projects/dcm4che/files/dcm4chee-arc-light5/ |
| Conformance Statement | https://dcm4chee-arc-cs.readthedocs.io/ |

---

## Certification Track

Setelah menyelesaikan course, peserta diharapkan mampu:

```
┌─────────────────────────────────────────────────────────────────┐
│                    CERTIFICATION LEVELS                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  DCM4CHEE Certified Implementer (Level 1-2 complete)            │
│  └── Dapat implement DCM4CHEE di RS Kecil-Menengah              │
│  └── Setup MWL dan HIS integration                              │
│  └── Basic troubleshooting dan maintenance                       │
│                                                                  │
│  DCM4CHEE Certified Administrator (Level 3 complete)            │
│  └── Dapat implement DCM4CHEE di RS Besar/Rujukan              │
│  └── Setup HA cluster dan disaster recovery                     │
│  └── Performance tuning dan advanced monitoring                  │
│  └── Security implementation (TLS, SSO)                         │
│  └── PACS migration dari sistem lama                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Prerequisites Checklist

**Hardware/Software:**

- [ ] Ubuntu 22.04 LTS Server (minimal 2GB RAM, 50GB disk)
- [ ] Docker Engine 24.0+
- [ ] Docker Compose v2
- [ ] Git
- [ ] Text editor (nano/vim/code)

**Optional (untuk advanced):**

- [ ] Second server untuk HA
- [ ] NAS/Shared storage
- [ ] SSL certificates
- [ ] Monitoring tools

**Knowledge:**

- [ ] Linux command line basics
- [ ] TCP/IP networking fundamentals
- [ ] Docker basics (ps, logs, exec, volumes)
- [ ] PostgreSQL basics (psql, basic queries)

---

## How to Use This Training Material

### Untuk Self-Study

1. **Baca** LEVEL-1-BASIC.md dari awal
2. **Kerjakan** hands-on exercises secara berurutan
3. **Verifikasi** setiap exercise sebelum lanjut ke modul berikutnya
4. **K完成** semua Level 1 sebelum mulai Level 2
5. **Diskusikan** studi kasus dengan instruktur/tim

### Untuk Instrukt-led Training (3-day intensive)

**Day 1 (8 jam):**
- Morning: Level 1 Modul 1-2 (Konsep DICOM + Arsitektur)
- Afternoon: Level 1 Modul 3-4 (Instalasi + Hands-on Exercises)

**Day 2 (8 jam):**
- Morning: Level 2 Modul 1-3 (PACS Config + MWL + HIS Integration)
- Afternoon: Level 2 Modul 4-6 (QR SCP + DB Optimization + Hands-on)

**Day 3 (8 jam):**
- Morning: Level 3 Modul 1-2 (Security + Backup/DR)
- Afternoon: Level 3 Modul 3-4 (HA + Performance) + Studi Kasus

### Untuk On-the-Job Reference

1. Gunakan **Quick Reference** di atas untuk command cepat
2. Cek **Modul Troubleshooting** di setiap level untuk issue spesifik
3. Rujuk **Studi Kasus** yang sesuai dengan ukuran RS Anda
4. Check **SKILL.md** untuk Docker deployment commands

---

## Contributing & Feedback

Materi ini dibuat berdasarkan:
- Official DCM4CHEE Wiki dan Documentation
- Best practices dari implementasi production di RS Indonesia
- DICOM Standard dan IHE Integration Profiles
- Real-world experience dari healthcare IT projects

**Feedback & contributions:**
- Issue tracker: GitHub Issues
- Pull requests welcome untuk improvement
- Studi kasus tambahan sangat dihargai

---

## Lisensi

Materi pembelajaran ini adalah **open source** dan bebas digunakan untuk keperluan pendidikan dan implementasi healthcare IT.

Referensi utama: [dcm4che/dcm4chee-arc-light](https://github.com/dcm4che/dcm4chee-arc-light) - Apache License 2.0

---

*Last updated: May 2026 | DCM4CHEE Archive 5.34.2 | Compatible with Ubuntu 22.04/24.04 LTS*