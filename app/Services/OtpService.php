<?php

namespace App\Services;

use App\Exceptions\OtpVerificationException;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    /**
     * Membuat kode OTP baru dan mengembalikan kode polosnya
     * untuk dikirim lewat email. Kode polos tidak pernah disimpan.
     */
    public function generate(User $user, string $purpose): string
    {
        EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        EmailOtp::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(
                config('systemgis.otp_ttl_minutes')
            ),
            'attempts' => 0,
        ]);

        return $code;
    }

    /**
     * @throws OtpVerificationException
     */
    public function verify(User $user, string $purpose, string $code): void
    {
        $otp = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new OtpVerificationException(
                'Kode verifikasi tidak ditemukan atau sudah digunakan. Silakan minta kode baru.'
            );
        }

        if (now()->greaterThan($otp->expires_at)) {
            throw new OtpVerificationException(
                'Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru.'
            );
        }

        if ($otp->attempts >= config('systemgis.otp_max_attempts')) {
            throw new OtpVerificationException(
                'Percobaan kode verifikasi sudah melebihi batas. Silakan minta kode baru.'
            );
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new OtpVerificationException(
                'Kode verifikasi salah.'
            );
        }

        $otp->forceFill(['consumed_at' => now()])->save();
    }
}
