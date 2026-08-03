<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventProxyTransform
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        /*
         * Edge proxy Hostinger (Server: hcdn) mengompres ulang respons dengan
         * zstd, dan hasilnya rusak untuk sebagian ukuran body: klien hanya
         * menerima potongan penutup HTML sehingga halaman tampak kosong.
         * gzip, brotli, dan respons tanpa kompresi tetap benar.
         *
         * no-transform adalah sinyal HTTP baku (RFC 9111) yang melarang
         * perantara mengubah encoding respons. Ditaruh paling luar pada grup
         * web supaya direktif ini ditambahkan setelah middleware lain selesai
         * menetapkan Cache-Control miliknya sendiri.
         */
        $response->headers->addCacheControlDirective('no-transform');

        return $response;
    }
}
