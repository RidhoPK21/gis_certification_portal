<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Exceptions\OtpVerificationException;
use App\Http\Controllers\Controller;
use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request)
    {
        return view('auth.reset-password', [
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, OtpService $otp, AuditLogger $audit)
    {
        $data = $request->validate(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'code' => ['required', 'digits:6'],
                'password' => $this->passwordRules(),
            ],
            [
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format alamat email tidak valid.',
                'code.required' => 'Kode reset wajib diisi.',
                'code.digits' => 'Kode reset terdiri dari 6 angka.',
                'password.required' => 'Kata sandi baru wajib diisi.',
                'password.confirmed' => 'Konfirmasi kata sandi tidak sesuai.',
            ]
        );

        $user = $this->findUser($data['email']);

        /*
         * Akun tidak ditemukan dan kode salah dijawab sama, agar endpoint
         * publik ini tidak bisa dipakai memetakan email yang terdaftar.
         */
        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'Kode reset tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        try {
            $otp->verify($user, EmailOtp::PURPOSE_PASSWORD_RESET, $data['code']);
        } catch (OtpVerificationException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        $audit->log('user.password_reset_completed', $user);

        return redirect()
            ->route('login')
            ->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru Anda.');
    }

    public function resend(Request $request, OtpService $otp)
    {
        $data = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:255']],
            [
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format alamat email tidak valid.',
            ]
        );

        $user = $this->findUser($data['email']);

        if ($user && $user->is_active) {
            $code = $otp->generate($user, EmailOtp::PURPOSE_PASSWORD_RESET);

            Mail::to($user->email)->send(
                new EmailOtpMail($user, $code, EmailOtp::PURPOSE_PASSWORD_RESET)
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->with(
                'status',
                'Jika email tersebut terdaftar, kode reset telah dikirim. '
                . 'Bila belum masuk dalam 1-2 menit, periksa folder Spam atau tab Promosi.'
            );
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])
            ->first();
    }
}
