# Panduan Uji Manual di Peramban — TCM-AUT-07, TCM-SKM-05, TCM-MST-04

Tiga butir uji ini sudah lolos pada pengujian sisi server (`tests/Manual/ManualTcTest.php`),
tetapi bagian yang dilihat pengguna hanya tampak di peramban. Panduan ini memuat langkah
lengkap untuk memeriksanya sendiri, beserta apa yang harus terlihat di layar.

Isi kolom **Result** dan **Status** pada subbab 7.4 dokumen `SW-KP-26-06-A.docx` setelah
setiap butir dijalankan.

---

## Persiapan (dilakukan sekali)

1. Buka berkas `.env`, pastikan dua baris berikut terisi:

   ```
   SYSTEMGIS_SEED_DEMO_ACCOUNTS=true
   SYSTEMGIS_DEMO_PASSWORD=DemoGis12345
   ```

2. Siapkan basis data beserta akun demo:

   ```
   php artisan gis:install --fresh --demo
   ```

3. Jalankan aplikasinya:

   ```
   php artisan serve
   ```

4. Buka `http://127.0.0.1:8000` di Google Chrome, jendela **lebar penuh**, mode terang.

Akun demo yang dipakai pada panduan ini:

| Peran | Email | Kata sandi |
|---|---|---|
| Klien | `client@systemgis.local` | `DemoGis12345` |
| Admin Permohonan | `admin.application@systemgis.local` | `DemoGis12345` |

> Kalau `.env` tidak memuat `MAIL_MAILER` yang mengarah ke SMTP asli, isi kode OTP dapat
> dibaca pada `storage/logs/laravel.log` — cari baris terakhir yang memuat enam angka.

---

## TCM-AUT-07 — Pengiriman ulang kode OTP melebihi batas

**Yang diperiksa:** tombol kirim ulang kode OTP hanya boleh dipakai **satu kali per menit**.

### Langkah

1. Pastikan sedang **tidak** masuk sebagai siapa pun. Kalau masih masuk, tekan **Keluar**.
2. Buka `http://127.0.0.1:8000/register`.
3. Isi formulir pendaftaran dengan data baru, misalnya:
   - Nama lengkap: `Uji Manual`
   - Nama perusahaan: `PT Uji Manual`
   - Email: `ujimanual01@example.com` (harus email yang belum pernah dipakai)
   - Kata sandi dan konfirmasinya: `RahasiaKuat123`
4. Selesaikan kotak verifikasi keamanan bila muncul, lalu tekan **Daftar**.
5. Halaman berpindah ke **Verifikasi Email**. Jangan isi kodenya dulu.
6. Tekan tombol **Kirim ulang kode**. Perhatikan: muncul pesan bahwa kode baru telah dikirim.
7. **Segera** tekan tombol **Kirim ulang kode** sekali lagi, dalam hitungan detik.

### Yang harus terlihat

- Penekanan **pertama** berhasil: muncul pesan kode telah dikirim ulang.
- Penekanan **kedua** ditolak: halaman menampilkan **429 Too Many Requests**, atau pesan
  yang meminta menunggu sebentar sebelum mencoba lagi.
- Tunggu lewat satu menit, lalu tekan lagi — kali ini berhasil lagi.

### Catatan

Batas yang terpasang adalah **1 permintaan per menit** dan **5 per jam**, terikat pada sesi
peramban. Jadi membuka jendela penyamaran (incognito) akan dihitung sebagai sesi berbeda dan
tidak ikut tertahan. Itu bukan kesalahan, memang begitu rancangannya.

---

## TCM-SKM-05 — Aturan kondisional menyembunyikan section

**Yang diperiksa:** pada skema ISPO, bagian formulir dan daftar dokumen berubah mengikuti
**Jenis Pemohon** yang dicentang.

### Langkah

1. Masuk sebagai **Klien** (`client@systemgis.local`).
2. Buka halaman **Permohonan Saya**, lalu tekan **＋ Ajukan Baru**.
3. Pada halaman **Pilih Skema Sertifikasi**, cari **Sertifikasi ISPO Terpadu**, tekan
   **Mulai Permohonan**.
4. Isi data awal (nama perusahaan, nama pemohon, email, telepon), lalu tekan
   **Buat Draft & Lanjut Isi Form**.
5. Halaman pengisian formulir terbuka. Gulir ke bagian **A. Ruang Lingkup Permohonan**.
6. Pada isian **Jenis Pemohon**, centang **hanya** `Perusahaan Perkebunan (Hulu)`.
7. Amati formulir, lalu gulir ke bawah sampai daftar dokumen.
8. Sekarang hapus centang itu, ganti dengan **hanya** `Industri Hilir`.
9. Amati lagi bagian formulir dan daftar dokumennya.

### Yang harus terlihat

Saat **Perusahaan Perkebunan (Hulu)** dicentang:

- Muncul bagian **H. Data Khusus Perusahaan Perkebunan**
- **Tidak** muncul bagian **I. Data Khusus Industri Hilir Kelapa Sawit**
- Daftar dokumen memuat **[Perusahaan Perkebunan] Perizinan berusaha perkebunan**
- Daftar dokumen **tidak** memuat **[Industri Hilir] Perizinan berusaha Industri Hilir…**

Saat diganti ke **Industri Hilir**, semuanya berkebalikan.

### Catatan

Boleh mencentang lebih dari satu jenis pemohon sekaligus. Bila `Perusahaan Perkebunan` dan
`Industri Hilir` dicentang bersamaan, **kedua** bagian dan **kedua** dokumen itu muncul.
Itu perilaku yang benar, karena aturannya memakai `contains`, bukan sama persis.

---

## TCM-MST-04 — Lingkup terisi otomatis dari nama kode NACE

**Yang diperiksa:** memilih kode NACE mengisi sendiri keterangan dan **Lingkup industri**,
sehingga pemohon tidak mengetik ulang.

### Langkah

1. Masuk sebagai **Klien** (`client@systemgis.local`).
2. Buka halaman **Permohonan Saya**, lalu tekan **＋ Ajukan Baru**.
3. Pada halaman **Pilih Skema Sertifikasi**, cari **Sertifikasi Sistem Manajemen Mutu
   SNI ISO 9001:2015**, tekan **Mulai Permohonan**.
4. Isi data awal, lalu tekan **Buat Draft & Lanjut Isi Form**.
5. Gulir ke bagian **2. Ruang Lingkup & Site**.
6. Pada dropdown **IAF Code**, pilih salah satu kode, misalnya `16`.
7. Perhatikan dropdown **NACE Code**: isinya berubah, hanya menampilkan kode NACE yang
   bernaung di bawah IAF tersebut.
8. Pada dropdown **NACE Code**, pilih salah satu, misalnya `23.6`.
9. Amati kolom **Lingkup industri** dan tulisan kecil di bawah dropdown NACE.
10. Simpan permohonan, lalu muat ulang halamannya.

### Yang harus terlihat

- Setelah **IAF Code** dipilih, pilihan **NACE Code** ikut menyempit — kode dari IAF lain
  tidak ditawarkan.
- Setelah **NACE Code** dipilih, di bawah dropdown muncul keterangannya, misalnya
  *"Industri pembuatan beton, semen dan gips"*.
- Kolom **Lingkup industri** **terisi sendiri** dengan keterangan yang sama, tanpa diketik.
- Kolom **Lingkup industri** tetap **bisa disunting** bila pemohon ingin menyebut lingkupnya
  lebih rinci. Perlu diketahui: mengganti kode NACE lagi akan **menimpa** suntingan itu,
  sedangkan sekadar memuat ulang halaman tidak menimpanya.
- Setelah disimpan dan halaman dimuat ulang, pilihan kode beserta keterangannya masih ada.

### Uji penolakan (opsional, perlu Peralatan Pengembang)

1. Tekan **F12** → tab **Console**.
2. Jalankan perintah berikut untuk memaksa kode yang tidak ada pada acuan KAN:

   ```js
   document.querySelector('[name="fields[iaf_code]"]').innerHTML =
     '<option value="999" selected>999</option>';
   ```

3. Tekan **Simpan**.

**Yang harus terlihat:** penyimpanan ditolak dengan pesan galat pada isian IAF Code, dan
keterangan apa pun yang sempat terisi **tidak** ikut tersimpan.

---

## Setelah selesai

Untuk setiap butir, tuliskan pada dokumen SW subbab 7.4:

- **Result** — apa yang benar-benar terlihat di layar, bukan salinan kolom Expected.
- **Status** — `Passed` bila sama dengan kolom Expected, `Failed` bila berbeda.

Bila ada yang `Failed`, sertakan tangkapan layarnya supaya mudah ditelusuri.
