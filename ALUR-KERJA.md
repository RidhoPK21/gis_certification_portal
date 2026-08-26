# Alur Kerja Sertifikasi

Satu permohonan ditelusuri dari `draft` sampai `surveillance`: siapa memegang
tahap mana, status apa yang berlaku, dan ke mana saja jalur baliknya.

Alur ini punya tiga definisi yang saling melengkapi di dalam kode, dan dokumen
ini menggabungkan ketiganya:

| Sumber | Yang didefinisikan |
|---|---|
| `app/Services/WorkflowService.php:14-39` | Peta `ALLOWED` — satu-satunya daftar transisi status yang sah |
| `database/seeders/WorkflowSeeder.php:31-41` | 9 langkah kerja: peran pemilik, SLA, dan langkah yang boleh dilewati |
| `app/Services/WorkflowService.php:161-192` | `publicTimeline()` — 9 tahap yang dilihat klien pada halaman pelacakan |

Tidak ada panah pada diagram di bawah yang tidak punya padanan di `ALLOWED`.

## Diagram

Versi gambar untuk bahan presentasi (4500 × 940 px):

![Alur kerja sertifikasi](alur-kerja-sertifikasi.png)

Versi draw.io untuk disunting sendiri: [`alur-kerja-sertifikasi.drawio`](alur-kerja-sertifikasi.drawio)
— buka lewat <https://app.diagrams.net> atau ekstensi Draw.io Integration di VS Code.
XML-nya sengaja tidak dikompresi supaya bisa dibaca dan di-`diff` seperti berkas
teks biasa.

Versi Mermaid, untuk disunting bila alurnya berubah:

```mermaid
flowchart TD
    draft["draft · Klien mengisi formulir"]:::klien
    submitted["submitted · Permohonan dikirim"]:::klien
    admin["admin_review · Review kelengkapan"]:::admin
    teknis["technical_review · Tinjauan teknis"]:::teknis
    revreq["revision_requested · Perbaikan diminta"]:::putar
    clirev["client_revision · Klien memperbaiki"]:::klien
    rejected["rejected · Permohonan berhenti"]:::tolak
    approved["application_approved · Disetujui Tim Teknis"]:::teknis
    invoice["invoice_process · Invoice diterbitkan"]:::finance
    partial["payment_partial · Pembayaran bertahap"]:::finance
    lunas["payment_completed · Lunas"]:::finance
    s1["stage_1_audit · Audit Tahap 1"]:::auditor
    s2["stage_2_audit · Audit Tahap 2"]:::auditor
    qms["qms_audit · Audit lapangan / QMS"]:::auditor
    ca["corrective_action · Temuan diterbitkan"]:::auditor
    carev["corrective_revision · Koreksi perlu diperbaiki"]:::putar
    certrev["certificate_review · Draft sertifikat"]:::teknis
    final["final_certificate · Sertifikat final terbit"]:::teknis
    done["completed · Sertifikasi selesai"]:::teknis
    surv["surveillance · Pengawasan berkala"]:::teknis

    draft --> submitted --> admin

    admin -->|teruskan| teknis
    admin -->|minta revisi| revreq
    teknis -->|minta revisi| revreq
    teknis -->|kembalikan ke Admin| admin
    teknis -->|tolak| rejected
    teknis -->|setujui| approved
    revreq --> clirev --> admin

    approved -.otomatis.-> invoice
    invoice --> partial
    partial -->|termin berikutnya| partial
    invoice -->|lunas sekaligus| lunas
    partial --> lunas

    lunas --> s1
    lunas -. Stage 1 dilewati .-> s2
    lunas -. Stage 1 + 2 dilewati .-> qms
    s1 --> s2
    s1 -. Stage 2 dilewati .-> qms
    s2 --> qms

    qms -->|ada temuan| ca
    qms -->|tanpa temuan| certrev
    ca --> carev
    carev -->|klien kirim ulang| ca
    ca -->|semua diterima| certrev

    certrev --> final --> done
    done -. bila jadwal terbentuk .-> surv

    classDef klien fill:#eaf5fc,stroke:#0b70b8,color:#082f54
    classDef admin fill:#e6eef6,stroke:#082f54,color:#082f54
    classDef teknis fill:#e4f0e6,stroke:#1b5e20,color:#14401a
    classDef finance fill:#fdf0e3,stroke:#b25e09,color:#6b3803
    classDef auditor fill:#ece9f7,stroke:#4c3d9e,color:#2c2363
    classDef putar fill:#fdf3d6,stroke:#b25e09,color:#6b3803
    classDef tolak fill:#fbe9e7,stroke:#b42318,color:#7a1811
```

## Tahap, pemilik, dan SLA

Sembilan langkah kerja beserta perannya, dari `WorkflowSeeder.php:31-41`:

| # | Langkah | Pemilik | SLA | Tahap publik |
|---|---|---|---|---|
| 1 | Application Form | Klien | 14 hari | Permohonan |
| 2 | Review Admin & Tinjauan | Admin Permohonan | 5 hari | Permohonan |
| 3 | Invoice & Pembayaran | Finance | 7 hari | Invoice & Pembayaran |
| 4 | Stage 1 Audit | Auditor | 10 hari | Audit Tahap 1 |
| 5 | Stage 2 Audit | Auditor | 15 hari | Audit Tahap 2 |
| 6 | QMS / Audit Lapangan | Auditor | 15 hari | Audit Lapangan / QMS |
| 7 | Corrective Action | Auditor | 30 hari | Tindakan Koreksi |
| 8 | Draft Certificate Review | Tim Teknis | 7 hari | Review Sertifikat |
| 9 | Sertifikat Final | Tim Teknis | 7 hari | Sertifikat Final |

Tahap **Surveillance** tidak punya langkah kerja tersendiri; ia diaktifkan
otomatis setelah sertifikasi selesai bila jadwalnya sudah terbentuk.

## Dua hal yang mudah salah dibaca

**Keputusan akhir ada di Tim Teknis, bukan Admin Permohonan.** Admin mengkaji
kelengkapan lalu meneruskan; hanya `technical_review` yang bisa mencapai
`application_approved` maupun `rejected`. Keduanya sama-sama boleh meminta
revisi ke klien. Lihat `WorkflowService.php:17-24`.

**ISPO dipegang tim yang berbeda.** Langkah admin dan teknis untuk skema ISPO
menjadi tanggung jawab `admin_sustain` dan `technical_sustain`, bukan
`admin_application` dan `technical`. Pemetaannya hidup di
`config/scheme_ownership.php` dan dibaca lewat `SchemeOwnershipService`
(`WorkflowSeeder.php:22-23`). Mengakses order ISPO dengan akun non-Sustain akan
ditolak 403.

## Langkah yang boleh dilewati

Stage 1 dan Stage 2 tidak berlaku sama untuk semua skema
(`WorkflowSeeder.php:29-30`):

| Kategori skema | Skema | Stage 1 | Stage 2 |
|---|---|---|---|
| Sistem manajemen | ISO 9001, 14001, 45001, 27001, 20000, 37001, 37301 | Wajib | Wajib |
| Keamanan pangan | ISO 22000, HACCP | Wajib | Wajib |
| Produk (LSPro) | SNI, SNI Lokal, SNI Impor | Boleh dilewati | Boleh dilewati |
| ISPO | ISPO | Boleh dilewati | Wajib |

Melewati sebuah tahap tetap menuntut alasan tertulis dan tanggal tindakan, dan
tercatat di riwayat status sebagai `audit_stage_skipped`
(`AuditController.php:173`).

## Perpindahan otomatis

Dua perpindahan terjadi tanpa ada orang yang menekan tombol keduanya:

- `application_approved` → `invoice_process`, langsung setelah Tim Teknis
  menyetujui dan PDF tinjauan selesai dibuat (`TechnicalController.php:270`)
- `completed` → `surveillance`, hanya bila jadwal surveillance sudah terbentuk
  dari sertifikat final (`TechnicalController.php:450`)

## Akun demo untuk menelusuri alur

Dibuat `SystemAccountsSeeder`, hanya pada environment `local`/`testing` dan
hanya bila `SYSTEMGIS_SEED_DEMO_ACCOUNTS=true`. Password seluruh akun diambil
dari `SYSTEMGIS_DEMO_PASSWORD` di `.env`.

| Email | Peran | Tahap yang dipegang |
|---|---|---|
| `client@systemgis.local` | Klien | Isi, kirim, dan perbaiki permohonan |
| `admin.application@systemgis.local` | Admin Permohonan | Review admin (selain ISPO) |
| `admin.sustain@systemgis.local` | Tim Admin Sustain | Review admin ISPO |
| `finance@systemgis.local` | Finance | Invoice & pembayaran |
| `auditor@systemgis.local` | Auditor | Stage 1, Stage 2, QMS, tindakan koreksi |
| `technical@systemgis.local` | Tim Teknis | Tinjauan teknis sampai sertifikat (selain ISPO) |
| `technical.sustain@systemgis.local` | Tim Teknis Sustain | Rantai penuh ISPO |
| `superadmin@systemgis.local` | Superadmin | Master data, termasuk acuan IAF/NACE |
