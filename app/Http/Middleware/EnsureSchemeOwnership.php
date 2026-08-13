<?php

namespace App\Http\Middleware;

use App\Models\CertificationApplication;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup akses internal ke permohonan yang bukan tanggung jawab penggunanya.
 *
 * Menyaring daftar saja tidak cukup: tanpa penjagaan ini order ISPO tetap
 * terbuka bagi Admin Permohonan lewat URL langsung. Dipasang pada grup route,
 * bukan ditaburkan sebagai abort_unless di puluhan aksi, supaya tidak ada
 * jalur yang terlewat saat aksi baru ditambahkan.
 *
 * Sebagian route tidak membawa {application} melainkan model anaknya — Surat
 * Tugas, draft sertifikat, jadwal surveillance, dan seterusnya — sehingga
 * parameter route ditelusuri sampai menemukan permohonan induknya.
 */
class EnsureSchemeOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $application = $this->permohonanDari($request);

        if ($application && ! $application->isHandledBy($user)) {
            abort(403, 'Permohonan ini ditangani tim lain sesuai pembagian skema.');
        }

        return $next($request);
    }

    /**
     * Permohonan yang sedang diakses, dicari dari parameter route.
     *
     * Halaman daftar tidak punya parameter permohonan; untuk itu penyaringannya
     * dilakukan scope handledBy() pada kueri, bukan di sini.
     */
    private function permohonanDari(Request $request): ?CertificationApplication
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof CertificationApplication) {
                return $parameter->loadMissing('scheme');
            }

            if ($parameter instanceof Model) {
                $induk = $this->indukDari($parameter);

                if ($induk) {
                    return $induk;
                }
            }
        }

        return null;
    }

    /**
     * Telusuri model anak ke permohonan induknya.
     *
     * Ditelusuri lewat relasi, bukan daftar kelas yang ditulis manual, agar
     * model anak baru ikut terjaga tanpa perlu diingat.
     */
    private function indukDari(Model $model): ?CertificationApplication
    {
        if (method_exists($model, 'application')) {
            $induk = $model->application;

            if ($induk instanceof CertificationApplication) {
                return $induk->loadMissing('scheme');
            }
        }

        /*
         * Dua tingkat: link berbagi sertifikat dan tindakan koreksi menempel
         * pada draft/final/temuan, bukan langsung pada permohonan.
         */
        foreach (['certificateDraft', 'certificateFinal', 'finding', 'auditStage', 'correctiveAction'] as $relasi) {
            if (! method_exists($model, $relasi)) {
                continue;
            }

            $perantara = $model->{$relasi};

            if ($perantara instanceof Model) {
                $induk = $this->indukDari($perantara);

                if ($induk) {
                    return $induk;
                }
            }
        }

        return null;
    }
}
