<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'Akun Anda sedang dinonaktifkan. Hubungi administrator SystemGIS.',
                ]);
        }

        $response = $next($request);

        /*
         * Halaman ter-autentikasi tidak boleh di-cache browser. Tanpa ini,
         * tombol Back bisa menampilkan halaman milik role sebelumnya dari
         * bfcache/HTTP cache setelah pengguna logout dan login sebagai role lain.
         */
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}