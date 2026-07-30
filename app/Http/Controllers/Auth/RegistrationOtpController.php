<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OtpVerificationException;
use App\Http\Controllers\Controller;
use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationOtpController extends Controller
{
    public function show(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'email' =>
                        'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.',
                ]);
        }

        return view('auth.register-verify', [
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    public function verify(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'email' =>
                        'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.',
                ]);
        }

        $data = $request->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Kode verifikasi wajib diisi.',
                'code.digits' => 'Kode verifikasi terdiri dari 6 angka.',
            ]
        );

        try {
            $otp->verify(
                $user,
                EmailOtp::PURPOSE_REGISTRATION,
                $data['code']
            );
        } catch (OtpVerificationException $e) {
            throw ValidationException::withMessages([
                'code' => $e->getMessage(),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $request->session()->forget('otp_pending_user_id');

        return redirect()
            ->route('login')
            ->with('registered', true)
            ->with(
                'status',
                'Email berhasil diverifikasi. Silakan masuk menggunakan email dan kata sandi Anda.'
            );
    }

    public function resend(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'email' =>
                        'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.',
                ]);
        }

        $code = $otp->generate(
            $user,
            EmailOtp::PURPOSE_REGISTRATION
        );

        Mail::to($user->email)->send(
            new EmailOtpMail($user, $code, EmailOtp::PURPOSE_REGISTRATION)
        );

        return back()->with(
            'status',
            'Kode verifikasi baru telah dikirim ke email Anda.'
        );
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('otp_pending_user_id');

        if (! $userId) {
            return null;
        }

        return User::query()
            ->whereNull('email_verified_at')
            ->find($userId);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(
            explode('@', $email, 2),
            2,
            ''
        );

        $visible = Str::substr($local, 0, 2);

        return $visible . str_repeat('*', max(Str::length($local) - 2, 1))
            . ($domain !== '' ? '@' . $domain : '');
    }
}
