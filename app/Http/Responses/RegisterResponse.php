<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Respons setelah pendaftaran Klien berhasil.
     */
    public function toResponse($request)
    {
        /*
         * Fortify otomatis melakukan login setelah registrasi.
         * Karena alur SystemGIS mengharuskan pengguna login
         * secara manual, sesi autentikasi langsung diakhiri.
         */
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->wantsJson()) {
            return new JsonResponse([
                'message' =>
                    'Pendaftaran berhasil. Silakan masuk menggunakan akun Anda.',
            ], 201);
        }

        return redirect()
            ->route('login')
            ->with('registered', true)
            ->with(
                'status',
                'Akun berhasil dibuat. Silakan masuk menggunakan email dan kata sandi Anda.'
            );
    }
}