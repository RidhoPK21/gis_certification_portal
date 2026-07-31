<?php

return [
    /*
     * Cloudflare Turnstile untuk menahan bot pada form publik
     * (login dan registrasi klien).
     *
     * Proteksi hanya aktif bila KEDUA kunci di bawah terisi, sehingga
     * pengembangan lokal dan test suite tidak perlu memanggil layanan luar.
     */
    'site_key' => env('TURNSTILE_SITE_KEY'),

    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    'verify_url' => env(
        'TURNSTILE_VERIFY_URL',
        'https://challenges.cloudflare.com/turnstile/v0/siteverify'
    ),

    /*
     * Batas waktu memanggil API Cloudflare (detik). Bila layanan tidak
     * merespons, permintaan ditolak agar tidak menjadi celah lolos.
     */
    'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
];
