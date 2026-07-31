<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Gambar branding: kunci setting => [batas KB, ekstensi yang diizinkan].
     */
    private const IMAGES = [
        'logo_path' => ['name' => 'logo', 'max' => 1024, 'mimes' => 'jpg,jpeg,png,webp,svg'],
        'login_logo_path' => ['name' => 'login_logo', 'max' => 1024, 'mimes' => 'jpg,jpeg,png,webp,svg'],
        'favicon_path' => ['name' => 'favicon', 'max' => 256, 'mimes' => 'png,ico,svg'],
    ];

    private const TEXTS = [
        'app_name', 'app_tagline', 'company_name',
        'login_heading', 'login_subheading',
        'footer_text', 'contact_address', 'contact_phone', 'contact_email',
    ];

    public function index(SettingService $settings)
    {
        return view('superadmin.settings', [
            'settings' => $settings->all(),

            // URL kustom (null bila belum diunggah) — menentukan tombol "Hapus".
            'logoUrl' => $settings->imageUrl('logo_path'),
            'loginLogoUrl' => $settings->imageUrl('login_logo_path'),
            'faviconUrl' => $settings->imageUrl('favicon_path'),

            // Gambar yang benar-benar tampil saat ini, termasuk bila masih bawaan.
            'effectiveLoginLogoUrl' => $settings->loginLogoUrl(),
            'effectiveFaviconUrl' => $settings->faviconUrl(),
        ]);
    }

    public function update(Request $request, SettingService $settings, AuditLogger $audit)
    {
        $rules = [
            'app_name' => ['required', 'string', 'max:60'],
            'app_tagline' => ['nullable', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:150'],
            'login_heading' => ['nullable', 'string', 'max:150'],
            'login_subheading' => ['nullable', 'string', 'max:300'],
            'footer_text' => ['nullable', 'string', 'max:300'],
            'contact_address' => ['nullable', 'string', 'max:300'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ];

        foreach (self::IMAGES as $config) {
            $rules[$config['name']] = ['nullable', 'file', 'mimes:' . $config['mimes'], 'max:' . $config['max']];
        }

        $data = $request->validate($rules, [
            'app_name.required' => 'Nama aplikasi wajib diisi.',
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'contact_email.email' => 'Format email kontak tidak valid.',
        ]);

        $values = [];
        foreach (self::TEXTS as $key) {
            $values[$key] = $data[$key] ?? '';
        }

        foreach (self::IMAGES as $key => $config) {
            if ($request->hasFile($config['name'])) {
                $values[$key] = $this->storeImage($request, $config['name'], $settings->get($key));
            }

            if ($request->boolean('remove_' . $config['name'])) {
                $this->deleteImage($settings->get($key));
                $values[$key] = '';
            }
        }

        $settings->put($values);

        $audit->log('system.settings_updated', null, [], ['keys' => array_keys($values)]);

        return back()->with('success', 'Pengaturan sistem berhasil disimpan.');
    }

    private function storeImage(Request $request, string $field, string $previousPath): string
    {
        $file = $request->file($field);
        $name = $field . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        $file->storeAs('', $name, 'branding');

        $this->deleteImage($previousPath);

        return $name;
    }

    private function deleteImage(string $path): void
    {
        if ($path !== '' && Storage::disk('branding')->exists($path)) {
            Storage::disk('branding')->delete($path);
        }
    }
}
