# Panduan Deploy — GIS Certification Portal

Panduan ini memuat perintah SSH lengkap untuk dua skenario: **Shared Hosting (cPanel/hPanel, mis. Hostinger)** dan **VPS (Ubuntu + Nginx)**.

Stack: **Laravel 13, PHP 8.3, MySQL**. Aplikasi ini **tidak memerlukan Node.js/npm** saat deploy — seluruh CSS ditulis inline di Blade, dan `@vite` hanya dipakai di `welcome.blade.php` yang tidak terpakai.

---

## 0. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| PHP | **8.3** (project dikunci ke platform 8.3 lewat `composer.json`) |
| Ekstensi PHP | `gd` (**wajib**, untuk tanda tangan elektronik — harus mendukung **JPEG dan PNG**), `mbstring`, `openssl`, `pdo_mysql`, `xml`, `ctype`, `fileinfo`, `curl`, `zip` |
| Database | MySQL/MariaDB |
| Akses SSH | **Wajib** — migrasi dan pembuatan superadmin memerlukan `php artisan` |
| Composer | 2.x |
| SMTP | Wajib di produksi; kode OTP dikirim sinkron saat pengguna menunggu |

> **Penting soal versi PHP.** `composer.json` memuat `config.platform.php = 8.3.30`, sehingga `composer.lock` selalu menghasilkan paket yang kompatibel dengan PHP 8.3. Jangan menjalankan `composer update` di mesin dengan PHP lebih baru tanpa menyesuaikan nilai itu — lock akan mengunci Symfony versi yang menuntut PHP ≥ 8.4 dan deploy akan gagal.

---

## A. Shared Hosting (Hostinger / cPanel)

### A1. Persiapan lewat panel (browser)

1. **Buat website** — pakai domain asli atau domain sementara (`*.hostingersite.com`).
2. **Set PHP 8.3** — hPanel → *Lanjutan → Konfigurasi PHP*. Di tab *PHP extensions*, aktifkan **`gd`**.
3. **Buat database MySQL** — catat **nama database**, **username**, dan **password**. Di Hostinger keduanya berawalan otomatis, contoh `u652037858_sistemgis`.
4. **Aktifkan SSH** — hPanel → *Lanjutan → Akses SSH*. Catat host, port (Hostinger memakai **65002**), dan username.

### A2. Masuk SSH

```bash
ssh -p 65002 USERNAME@HOST_IP
```

### A3. Ambil kode

```bash
cd ~
git clone -b main https://github.com/RidhoPK21/gis_certification_portal.git gis_app
cd gis_app
```

> Project **harus** berada di luar `public_html`. Kalau seluruh project ditaruh di dalam folder publik, file `.env` (berisi password database dan kredensial SMTP) dapat diunduh siapa pun lewat browser.

### A4. Buat `.env`

```bash
nano .env
```

Isi berdasarkan contoh di bagian **D. Referensi `.env`**, lalu simpan (`Ctrl+O` → `Enter` → `Ctrl+X`).

Bersihkan karakter carriage return bila isinya ditempel dari Windows — kalau tidak, nilai seperti password akan terbaca sebagai `password\r` dan selalu ditolak:

```bash
sed -i 's/\r$//' .env
```

### A5. Install dependency

```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
rm composer-setup.php
php -d memory_limit=-1 composer.phar install --no-dev --optimize-autoloader
```

Bila hosting sudah menyediakan composer global, cukup `composer install --no-dev --optimize-autoloader`.

### A6. Siapkan aplikasi

```bash
php artisan key:generate
chmod -R 775 storage bootstrap/cache
php artisan gis:install
```

`gis:install` menjalankan migrasi, seeder inti (role & permission, katalog skema, workflow, taksonomi produk SNI), lalu membuat **satu akun superadmin** dari `.env`. Bila `GIS_ADMIN_PASSWORD` dikosongkan, password acak dicetak **sekali saja** — salin segera.

> Jalankan `gis:install` **sebelum** `config:cache`. Saat config di-cache, Laravel berhenti membaca `.env`, sehingga `GIS_ADMIN_*` tidak terbaca.

### A7. Arahkan document root ke folder `public`

Cari nama folder domain:

```bash
ls ~/domains
```

Lalu ganti `public_html` dengan symlink:

```bash
cd ~/domains/NAMA-DOMAIN-ANDA
rm -rf public_html
ln -s ~/gis_app/public public_html
ls -la | grep public_html
```

Harus terlihat `public_html -> /home/USERNAME/gis_app/public`.

Bila panel menyediakan opsi *custom folder* untuk domain, arahkan saja ke `gis_app/public` — lebih rapi daripada symlink.

Bila symlink tidak diizinkan, salin isi `public/` ke `public_html`, lalu ubah dua baris path di `public_html/index.php`:

```php
require __DIR__.'/../../../gis_app/vendor/autoload.php';
$app = require_once __DIR__.'/../../../gis_app/bootstrap/app.php';
```

### A8. Folder unggahan logo

Logo dan favicon dari menu *Pengaturan Sistem* disimpan di `public/branding` (sengaja tidak lewat `storage:link`, yang kerap bermasalah di shared hosting):

```bash
cd ~/gis_app
mkdir -p public/branding
chmod 775 public/branding
```

Folder ini diabaikan git, jadi isinya tidak akan tertimpa saat `git pull`.

### A9. Optimalkan & SSL

```bash
cd ~/gis_app
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pasang SSL lewat panel (*Security → SSL*) dan aktifkan **Force HTTPS**.

### A10. Cron (opsional)

Notifikasi portal dikirim lewat queue. Bila `QUEUE_CONNECTION=sync` (disarankan di shared hosting), langkah ini tidak diperlukan. Bila memakai `database`, tambahkan cron tiap 5 menit:

```
cd ~/gis_app && php artisan queue:work --stop-when-empty
```

Kode OTP **tidak** bergantung pada queue — selalu dikirim sinkron.

---

## B. VPS (Ubuntu 22.04/24.04 + Nginx)

### B1. Paket dasar

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl \
  php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-bcmath
```

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### B2. Database

```bash
sudo mysql -e "CREATE DATABASE gis_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'gisuser'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';"
sudo mysql -e "GRANT ALL PRIVILEGES ON gis_portal.* TO 'gisuser'@'localhost'; FLUSH PRIVILEGES;"
```

### B3. Ambil kode

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone -b main https://github.com/RidhoPK21/gis_certification_portal.git gis_app
sudo chown -R $USER:www-data /var/www/gis_app
cd /var/www/gis_app
```

### B4. Dependency & aplikasi

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env          # isi sesuai bagian D
php artisan key:generate
```

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan gis:install
```

### B5. Nginx

```bash
sudo nano /etc/nginx/sites-available/gis_portal
```

```nginx
server {
    listen 80;
    server_name portal.domain-anda.com;
    root /var/www/gis_app/public;

    index index.php;
    charset utf-8;
    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/gis_portal /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### B6. SSL

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d portal.domain-anda.com
```

### B7. Folder unggahan logo & optimalkan

```bash
cd /var/www/gis_app
mkdir -p public/branding
sudo chown -R www-data:www-data public/branding
sudo chmod 775 public/branding
```

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### B8. Queue worker & backup (opsional)

```bash
sudo nano /etc/supervisor/conf.d/gis-queue.conf
```

```ini
[program:gis-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gis_app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/gis_app/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start gis-queue:*
```

Backup mingguan lewat cron (`crontab -e`):

```
0 2 * * 0 cd /var/www/gis_app && php artisan gis:weekly-backup
```

---

## C. Update rutin (kedua skenario)

```bash
cd ~/gis_app            # VPS: cd /var/www/gis_app
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=SchemeCatalogSeeder --force
php artisan db:seed --class=GisFormTemplateSeeder --force
mkdir -p public/branding && chmod 775 public/branding
php artisan cache:clear
php artisan config:clear && php artisan config:cache
php artisan route:cache && php artisan view:cache
```

Catatan tiap langkah:

- **`migrate --force` jangan dilewatkan.** Identitas portal dibaca pada setiap halaman; bila ada migrasi baru yang belum dijalankan, aplikasi memang tetap hidup dengan nilai bawaan, tetapi menu *Pengaturan Sistem* tidak akan berfungsi.
- **`SchemeCatalogSeeder`** memuat katalog skema dari `database/seeders/data/schemes.json`: skema, bagian formulir, field, dan daftar dokumen wajib. Wajib dijalankan setiap kali rilis menambah atau mengubah skema — tanpa ini skema baru tidak akan muncul di portal. Seeder ini **idempoten**: memakai `updateOrCreate`, jadi aman diulang. Skema yang digantikan versi baru ditandai `"active": false` (bukan dihapus), sehingga permohonan lama tetap bisa dibuka.
- **`GisFormTemplateSeeder`** mendaftarkan berkas *Form Wajib GIS* yang diunduh klien. **Wajar bila lambat** (bisa beberapa menit): tiap berkas `.doc`/`.docx` disalin ke storage dan dihitung checksum-nya. Biarkan sampai selesai, jangan dihentikan di tengah.
- **`mkdir public/branding`** memastikan unggahan logo tidak gagal karena folder belum ada.
- **`cache:clear`** membuang cache pengaturan lama agar perubahan branding langsung terlihat.
- Urutan `config:clear` lalu `config:cache` — jangan dibalik.

> **Jangan pernah menjalankan `php artisan test` di server.** Sebagian test memakai `RefreshDatabase` (mengosongkan seluruh database) dan menghapus direktori `applications/{id}` pada disk `private` — sementara ID permohonan ikut tereset ke 1, sehingga **dokumen klien yang sudah diunggah bisa ikut terhapus**. Test hanya dijalankan di mesin pengembang.

Bila di server pernah ada perubahan file manual, buang dulu supaya `git pull` tidak bentrok:

```bash
git checkout -- .
```

`.env`, `vendor/`, dan `public/branding/` tidak dilacak git, jadi tidak akan tertimpa.

---

## D. Referensi `.env`

```dotenv
APP_NAME="SystemGIS"
APP_ENV=production
APP_KEY=                                  # diisi oleh php artisan key:generate
APP_DEBUG=false
APP_URL=https://portal.domain-anda.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync                     # shared hosting; VPS dengan worker boleh "database"
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

# SMTP — wajib di produksi
MAIL_MAILER=smtp
MAIL_HOST=smtp.penyedia-anda.com
MAIL_PORT=587
MAIL_SCHEME=smtp                          # 587 = "smtp", 465 = "smtps". Nilai "tls" TIDAK valid.
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@domain-anda.com"
MAIL_FROM_NAME="${APP_NAME}"

# Identitas & akun demo
SYSTEMGIS_COMPANY_NAME="PT Global Inspeksi Sertifikasi"
SYSTEMGIS_COMPANY_SHORT_NAME="GIS"
SYSTEMGIS_SEED_DEMO_ACCOUNTS=false        # WAJIB false di produksi
SYSTEMGIS_DEMO_PASSWORD=

# OTP verifikasi email
SYSTEMGIS_OTP_TTL_MINUTES=10              # masa berlaku kode
SYSTEMGIS_OTP_MAX_ATTEMPTS=5              # batas salah sebelum kode hangus

# Superadmin pertama (dipakai sekali oleh gis:install)
GIS_ADMIN_NAME="Nama Superadmin"
GIS_ADMIN_EMAIL=                          # wajib email asli yang bisa diakses
GIS_ADMIN_PASSWORD=                       # kosong = digenerate acak, ditampilkan sekali

# Cloudflare Turnstile (anti-bot login & registrasi) — opsional
# Proteksi aktif hanya bila KEDUA kunci diisi.
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

Batas kirim ulang OTP (1×/menit, 5×/jam) dan batas percobaan login (5×/menit) diatur di `app/Providers/FortifyServiceProvider.php`, bukan lewat `.env`.

---

## E. Setelah deploy

1. Buka portal → login sebagai superadmin (email di `GIS_ADMIN_EMAIL`, password dari `gis:install`).
2. **Ganti password superadmin** lewat menu Profil.
3. Uji pengiriman email: **User & Role → Tambah Akun** dengan email asli → kode aktivasi harus masuk ke inbox.
4. Uji registrasi klien di `/register` → kode OTP → verifikasi → login.
5. Buka **Pengaturan Sistem** → unggah logo & favicon perusahaan, isi teks footer dan kontak. Bila halaman ini error, berarti migrasi belum dijalankan (`php artisan migrate --force`).
6. Buka **User & Role → Kelola** pada satu akun → coba **Kirim Kode Reset** dan pastikan emailnya masuk.
7. Pastikan dropdown **Produk & Kategori SNI** terisi (12 grup, 139 kategori). Bila kosong:
   ```bash
   php artisan db:seed --class=SniProductTaxonomySeeder --force
   ```

Akun staf **tidak dibuat lewat seeder**. Superadmin mengundang lewat **User & Role → Tambah Akun**; staf menerima kode aktivasi dan menentukan kata sandinya sendiri.

Beberapa hal yang mungkin ditanyakan pengguna:

- **Kode OTP masuk folder spam.** Wajar bila pengirimnya akun Gmail untuk domain berbeda. Aplikasi sudah menampilkan pemberitahuan agar pengguna memeriksa folder Spam/Promosi, tetapi solusi jangka panjangnya adalah memakai email domain perusahaan dengan SPF & DKIM yang benar.
- **Akun tidak dapat dihapus.** Disengaja: akun yang sudah memiliki permohonan tidak boleh dihapus, karena penghapusannya akan ikut menghapus seluruh permohonan, dokumen, invoice, dan sertifikat terkait. Nonaktifkan akunnya saja.

---

## F. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Composer detected issues in your platform: ... requires PHP >= 8.4.1` | Folder `vendor` dibawa dari mesin lain. Hapus `vendor`, jalankan `composer install` di server. Pastikan `config.platform.php` di `composer.json` = versi PHP server. |
| `Your lock file does not contain a compatible set of packages` | `composer.lock` dibuat di PHP lebih baru. Perbaiki di repo: set `config.platform.php`, jalankan `composer update`, commit lock-nya. Jangan `composer update` per server. |
| `SQLSTATE[HY000] [1045] Access denied` | Kredensial DB salah, **atau** ada `\r` di `.env`. Uji manual: `mysql -u USER -p DBNAME -e "SELECT 1;"`. Bila manual berhasil, jalankan `sed -i 's/\r$//' .env`. |
| `Identifier name '...' is too long` | Nama index melebihi 64 karakter (batas MySQL; tidak muncul di SQLite). Beri nama eksplisit pada `$table->unique([...], 'nama_pendek')`. |
| `The "tls" scheme is not supported` | `MAIL_SCHEME` harus `smtp` (port 587) atau `smtps` (port 465). |
| Halaman 500 / putih | `tail -50 storage/logs/laravel.log` |
| Perubahan `.env` tidak berpengaruh | `php artisan config:clear` lalu `php artisan config:cache` |
| CSS berantakan / tautan salah | `APP_URL` tidak sesuai domain, atau domain memakai bentuk subfolder (`/~username`) |
| Email OTP tidak terkirim | Uji: `php artisan tinker --execute="Mail::raw('tes', fn($m) => $m->to('anda@email.com')->subject('Tes'));"` Beberapa shared hosting memblokir SMTP keluar — pakai SMTP milik hosting tersebut. |
| Turnstile menolak terus | Hostname domain belum didaftarkan di widget Cloudflare, atau ada `\r` pada `TURNSTILE_SECRET_KEY`. |
| Menu **Pengaturan Sistem** error | Tabel `settings` belum ada — jalankan `php artisan migrate --force`. Halaman lain tetap berjalan dengan identitas bawaan. |
| Unggah logo gagal / logo tidak muncul | Folder `public/branding` belum ada atau tidak bisa ditulis: `mkdir -p public/branding && chmod 775 public/branding`. Pada VPS, pastikan pemiliknya `www-data`. |
| Logo sudah diganti tapi tampilan lama | Cache pengaturan: `php artisan cache:clear`. |
| Skema baru tidak muncul di portal | `SchemeCatalogSeeder` belum dijalankan setelah `git pull`: `php artisan db:seed --class=SchemeCatalogSeeder --force`. |
| Tanda tangan tidak muncul di PDF (kotaknya kosong, nama tetap tercetak) | `gd` tidak mendukung PNG. PDF hanya menerima JPEG, jadi unggahan PNG/GIF/WEBP dikonversi lebih dulu; bila `imagecreatefrompng` tidak ada, gambar dilewati diam-diam agar PDF tetap terbit. Cek: `php -r "var_dump(function_exists('imagecreatefrompng'));"` |

> **Jangan pernah** menyalakan `APP_DEBUG=true` di server publik — halaman error Laravel menampilkan seluruh isi `.env`, termasuk password.

---

## G. Yang tidak boleh diunggah ke server

`vendor/`, `node_modules/`, `.env`, `.git/` (bila mengunggah manual), `database/database.sqlite`, `storage/logs/*.log`, `.phpunit.result.cache`, `public/hot`, `public/branding/` (isi unggahan server, jangan ditimpa dari lokal).

Cara paling aman adalah `git clone` — semuanya sudah dikecualikan lewat `.gitignore`.
