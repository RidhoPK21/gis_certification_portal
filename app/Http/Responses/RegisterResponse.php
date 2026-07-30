<?php

namespace App\Http\Responses;

use App\Models\EmailOtp;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailOtpMail;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private OtpService $otp)
    {
    }

    /**
     * Respons setelah pendaftaran Klien berhasil.
     */
    public function toResponse($request)
    {
        $user = $request->user();

        /*
         * Fortify otomatis melakukan login setelah registrasi.
         * Karena alur SystemGIS mengharuskan verifikasi email
         * lebih dahulu, sesi autentikasi langsung diakhiri.
         */
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            /*
             * Penanda ini harus ditulis setelah invalidate(),
             * karena invalidate() menghapus seluruh isi sesi.
             */
            $request->session()->put('otp_pending_user_id', $user->id);
        }

        $code = $this->otp->generate(
            $user,
            EmailOtp::PURPOSE_REGISTRATION
        );

        /*
         * Dikirim sinkron, bukan queue: kode OTP dibutuhkan saat
         * pengguna masih menunggu di halaman verifikasi, sementara
         * queue database baru terkirim bila ada worker yang jalan.
         */
        Mail::to($user->email)->send(
            new EmailOtpMail($user, $code, EmailOtp::PURPOSE_REGISTRATION)
        );

        if ($request->wantsJson()) {
            return new JsonResponse([
                'message' =>
                    'Pendaftaran berhasil. Kode verifikasi telah dikirim ke email Anda.',
            ], 201);
        }

        return redirect()
            ->route('register.verify.show')
            ->with(
                'status',
                'Kode verifikasi telah dikirim ke email Anda.'
            );
    }
}
