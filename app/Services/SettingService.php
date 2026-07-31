<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    private const CACHE_KEY = 'systemgis.settings';

    /**
     * Nilai bawaan bila superadmin belum mengatur apa pun. Nama perusahaan
     * mengikuti config agar tetap konsisten dengan .env pada instalasi baru.
     */
    public function defaults(): array
    {
        return [
            'app_name' => 'SystemGIS',
            'app_tagline' => 'Certification Portal',
            'company_name' => config('systemgis.company_name'),
            'login_heading' => 'Portal Permohonan Sertifikasi Terintegrasi',
            'login_subheading' => 'Mengelola permohonan, peninjauan dokumen, pembayaran, audit, hingga penerbitan sertifikat dalam satu sistem.',
            'footer_text' => '© ' . date('Y') . ' ' . config('systemgis.company_name') . '. Seluruh hak cipta dilindungi.',
            'contact_address' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'logo_path' => '',
            'login_logo_path' => '',
            'favicon_path' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        try {
            $stored = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all()
            );
        } catch (\Throwable $e) {
            /*
             * Tabel settings belum ada (migrasi belum dijalankan saat deploy)
             * atau database sedang tidak dapat dihubungi. Kembalikan nilai
             * bawaan agar seluruh halaman tetap tampil, bukan error 500.
             * Hasil gagal sengaja tidak di-cache supaya pulih sendiri
             * begitu migrasi selesai.
             */
            return $this->defaults();
        }

        return array_merge($this->defaults(), array_filter(
            $stored,
            fn ($value) => $value !== null && $value !== ''
        ));
    }

    public function get(string $key, ?string $fallback = null): string
    {
        return (string) ($this->all()[$key] ?? $fallback ?? '');
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Logo bawaan yang dipakai bila superadmin belum mengunggah apa pun.
     */
    public function defaultLogoUrl(): string
    {
        return asset('images/gis-logo.png');
    }

    /**
     * Logo halaman login yang sedang berlaku (kustom bila ada, selain itu bawaan).
     */
    public function loginLogoUrl(): string
    {
        return $this->imageUrl('login_logo_path') ?? $this->defaultLogoUrl();
    }

    /**
     * Favicon yang sedang berlaku. Bawaannya memakai logo GIS karena
     * public/favicon.ico kosong, sehingga tab browser tidak polos.
     */
    public function faviconUrl(): string
    {
        return $this->imageUrl('favicon_path') ?? $this->defaultLogoUrl();
    }

    /**
     * URL gambar branding, atau null bila belum diunggah / file hilang.
     */
    public function imageUrl(string $key): ?string
    {
        $path = $this->get($key);

        if ($path === '') {
            return null;
        }

        try {
            if (! Storage::disk('branding')->exists($path)) {
                return null;
            }

            return asset('branding/' . $path) . '?v=' . Storage::disk('branding')->lastModified($path);
        } catch (\Throwable $e) {
            // Folder branding belum ada atau tidak terbaca — jatuh ke logo bawaan.
            return null;
        }
    }
}
