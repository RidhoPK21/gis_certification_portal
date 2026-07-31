<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TurnstileService
{
    public const FIELD = 'cf-turnstile-response';

    /**
     * Proteksi hanya berjalan bila kedua kunci terisi. Dengan begitu
     * lingkungan lokal dan test tidak memanggil layanan Cloudflare.
     */
    public function enabled(): bool
    {
        return filled(config('turnstile.site_key'))
            && filled(config('turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('turnstile.site_key');
    }

    /**
     * Memvalidasi token dari widget. Melempar ValidationException dengan
     * pesan Bahasa Indonesia bila gagal, agar tampil di form seperti
     * error validasi biasa.
     *
     * @throws ValidationException
     */
    public function ensureValid(Request $request, string $errorField = self::FIELD): void
    {
        if (! $this->enabled()) {
            return;
        }

        $token = (string) $request->input(self::FIELD, '');

        if ($token === '' || ! $this->verify($token, $request->ip())) {
            throw ValidationException::withMessages([
                $errorField => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.',
            ]);
        }
    }

    public function verify(string $token, ?string $ip = null): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(config('turnstile.timeout'))
                ->post(config('turnstile.verify_url'), array_filter([
                    'secret' => config('turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            /*
             * Ditolak, bukan diloloskan: kalau Cloudflare tidak dapat
             * dihubungi, meloloskan permintaan akan membuka celah bot.
             */
            Log::warning('Turnstile tidak dapat dihubungi.', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful()
            && (bool) $response->json('success', false);
    }
}
