# GIS Certification Portal

Portal sertifikasi **PT Global Inspeksi Sertifikasi (GIS)** — aplikasi web untuk mengelola alur permohonan sertifikasi dari klien mengajukan permohonan, review admin, penerbitan invoice & pembayaran (Finance), audit, penerbitan sertifikat, sampai surveillance.

Dibangun dengan **Laravel 13 (PHP 8.3+)**, Blade + Vite/Tailwind, dan Laravel Fortify untuk autentikasi.

---

## 1. Kebutuhan (Prasyarat)

Pastikan sudah terpasang di komputer masing-masing:

| Tool | Versi minimal | Cek |
|------|---------------|-----|
| PHP | 8.3+ (disarankan 8.3/8.4) dengan ekstensi `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo` | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js + npm | Node 18+ | `node -v` |
| Git | — | `git --version` |

Database default = **SQLite** (tidak perlu install server DB). Bisa diganti MySQL bila mau (lihat bagian 5).

---

## 2. Setup Cepat (dev, dengan akun demo)

> Contoh perintah memakai **PowerShell (Windows)**. Untuk macOS/Linux, ganti `Copy-Item` → `cp` dan `New-Item ... database.sqlite` → `touch database/database.sqlite`.

```powershell
# 1. Clone & masuk folder
git clone <URL-REPO> gis_certification_portal
cd gis_certification_portal

# 2. Install dependency PHP & JS
composer install
npm install

# 3. Siapkan file environment
Copy-Item .env.example .env
php artisan key:generate

# 4. Buat file database SQLite kosong
New-Item -ItemType File database/database.sqlite

# 5. Aktifkan akun demo di .env (lihat bagian 3)
#    - set SYSTEMGIS_SEED_DEMO_ACCOUNTS=true
#    - set SYSTEMGIS_DEMO_PASSWORD=DemoGis12345

# 6. Migrasi + seed (buat tabel, data skema, role, dan akun demo)
php artisan gis:install --fresh --demo

# 7. Build asset front-end
npm run build

# 8. Jalankan aplikasi
php artisan serve
```

Buka **http://127.0.0.1:8000** → login dengan salah satu akun demo di bagian 4.

Saat sedang aktif mengembangkan front-end (hot reload), jalankan `npm run dev` di terminal terpisah menggantikan langkah build.

---

## 3. Konfigurasi `.env` penting

Setelah `Copy-Item .env.example .env`, cukup ubah/isi bagian ini agar akun demo ikut dibuat saat seeding:

```dotenv
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=sqlite
# (biarkan default SQLite; path otomatis ke database/database.sqlite)

# Akun demo hanya dibuat jika kedua baris ini diisi & environment local/testing
SYSTEMGIS_SEED_DEMO_ACCOUNTS=true
SYSTEMGIS_DEMO_PASSWORD=DemoGis12345
```

> ⚠️ **Keamanan:** akun demo TIDAK akan pernah dibuat di `APP_ENV=production`. Password demo bebas ditentukan lewat `SYSTEMGIS_DEMO_PASSWORD`; contoh di dokumen ini memakai `DemoGis12345`.

---

## 4. Akun demo untuk development

Semua akun memakai password yang kamu isi di `SYSTEMGIS_DEMO_PASSWORD` (contoh: **`DemoGis12345`**).

| Peran | Email | Fungsi utama |
|-------|-------|--------------|
| Klien | `client@systemgis.local` | Mengisi & submit permohonan, upload dokumen, tindakan koreksi |
| Admin Permohonan | `admin.application@systemgis.local` | Review permohonan, revisi, approve/reject → teruskan ke Finance |
| Finance | `finance@systemgis.local` | Terbitkan invoice, catat pembayaran, atur status pembayaran |
| Auditor | `auditor@systemgis.local` | Input tahap audit, temuan, review tindakan koreksi |
| Teknis | `technical@systemgis.local` | Sertifikat (draft/final), review, jadwal surveillance |
| Superadmin | `superadmin@systemgis.local` | Kelola skema/Form Builder, produk SNI, user & role, audit trail |

### Alur uji singkat klien → Finance (2 klik saja)
1. Login **client** → `/client/applications/schemes` → pilih skema → isi draft → **Submit** (status: draft → admin_review otomatis).
2. Login **admin.application** → `/internal/applications` → buka order → **Setujui & Generate PDF** (status → invoice_process).
3. Login **finance** → `/internal/finance` → **Proses** order → terbitkan invoice & atur status pembayaran.

---

## 5. (Opsional) Pakai MySQL alih-alih SQLite

Isi `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gis_portal
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `gis_portal` di MySQL, lalu jalankan ulang `php artisan gis:install --fresh --demo`.

---

## 6. Perintah yang sering dipakai

| Perintah | Fungsi |
|----------|--------|
| `php artisan serve` | Menjalankan server dev di http://127.0.0.1:8000 |
| `npm run dev` | Vite hot-reload (front-end) |
| `npm run build` | Build asset produksi |
| `php artisan gis:install --fresh --demo` | Reset DB + migrasi + seed lengkap (termasuk akun demo) |
| `php artisan migrate` | Jalankan migrasi baru saja (tanpa reset) |
| `php artisan db:seed --class=SchemeCatalogSeeder` | Re-seed katalog skema/form (mis. setelah ubah `schemes.json`) |
| `php artisan test` | Jalankan seluruh test suite |
| `php artisan gis:import-sni-products <file>` | Import master produk SNI dari CSV/XLSX |
| `php artisan gis:generate-review <id-order>` | Generate PDF tinjauan permohonan (ID/UUID/nomor order) |
| `php artisan gis:weekly-backup` | Backup mingguan (arsip DB & storage privat) |

---

## 7. Struktur singkat & catatan

- **Seeder inti** (aman di semua environment): `RolePermissionSeeder`, `SchemeCatalogSeeder`, `WorkflowSeeder`. Akun demo dari `SystemAccountsSeeder` hanya jalan di local/testing bila diaktifkan di `.env`.
- **Definisi form permohonan** ada di `database/seeders/data/schemes.json` — setelah diubah, jalankan `php artisan db:seed --class=SchemeCatalogSeeder` agar tersimpan ke database.
- **File privat** (dokumen, invoice, sertifikat, backup) disimpan di `storage/app/private/...` dan diakses lewat route aman, bukan URL publik.
- **Test**: 56 test (PHPUnit). Jalankan `php artisan test` sebelum push.

---

## 8. Troubleshooting

| Gejala | Solusi |
|--------|--------|
| `could not find driver` saat migrate | Aktifkan ekstensi `pdo_sqlite` di `php.ini` |
| Halaman tampil tanpa style | Jalankan `npm run build` (atau `npm run dev`) |
| Akun demo tidak ada setelah seed | Pastikan `APP_ENV=local`, `SYSTEMGIS_SEED_DEMO_ACCOUNTS=true`, dan `SYSTEMGIS_DEMO_PASSWORD` terisi, lalu seed ulang |
| `419 Page Expired` saat submit | Refresh halaman (token CSRF kedaluwarsa) lalu ulangi |
| Perubahan `.env` tidak terbaca | `php artisan config:clear` |
