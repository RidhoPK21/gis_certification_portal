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

class AccountActivationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request)
    {
        return view('auth.activate-account', [
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function activate(
        Request $request,
        OtpService $otp,
        AuditLogger $audit
    ) {
        $data = $request->validate(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'code' => ['required', 'digits:6'],
                'password' => $this->passwordRules(),
            ],
            [
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format alamat email tidak valid.',
                'code.required' => 'Kode aktivasi wajib diisi.',
                'code.digits' => 'Kode aktivasi terdiri dari 6 angka.',
                'password.required' => 'Kata sandi wajib diisi.',
                'password.confirmed' => 'Konfirmasi kata sandi tidak sesuai.',
            ]
        );

        $email = Str::lower(trim($data['email']));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        /*
         * Akun yang tidak ada dan akun yang sudah aktif dijawab
         * dengan pesan yang sama, agar endpoint publik ini tidak
         * bisa dipakai memetakan email mana yang terdaftar.
         */
        if (! $user || $user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'code' =>
                    'Kode aktivasi tidak valid atau akun sudah diaktifkan.',
            ]);
        }

        try {
            $otp->verify(
                $user,
                EmailOtp::PURPOSE_ADMIN_INVITE,
                $data['code']
            );
        } catch (OtpVerificationException $e) {
            throw ValidationException::withMessages([
                'code' => $e->getMessage(),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        $audit->log('user.activated', $user);

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Akun berhasil diaktifkan. Silakan masuk menggunakan email dan kata sandi baru Anda.'
            );
    }

    public function resend(Request $request, OtpService $otp)
    {
        $data = $request->validate(
            [
                'email' => ['required', 'string', 'email', 'max:255'],
            ],
            [
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format alamat email tidak valid.',
            ]
        );

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower(trim($data['email']))])
            ->whereNull('email_verified_at')
            ->first();

        if ($user) {
            $code = $otp->generate(
                $user,
                EmailOtp::PURPOSE_ADMIN_INVITE
            );

            Mail::to($user->email)->send(
                new EmailOtpMail($user, $code, EmailOtp::PURPOSE_ADMIN_INVITE)
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->with(
                'status',
                'Jika email tersebut terdaftar dan menunggu aktivasi, kode baru telah dikirim.'
            );
    }
}
