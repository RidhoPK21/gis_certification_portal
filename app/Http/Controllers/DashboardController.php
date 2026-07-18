<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard ringkas per-role.
     *
     * Statistik modul (permohonan, invoice, audit, sertifikat)
     * akan diisi pada fase berikutnya setelah tabel domain tersedia.
     * Untuk sekarang dashboard menampilkan sambutan, role aktif,
     * dan pintasan menu yang sesuai hak akses.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing('roles');

        $userRoles = $user->roles->pluck('code')->all();

        $shortcuts = collect(config('navigation'))
            ->reject(fn (array $item): bool => $item['route'] === 'dashboard')
            ->filter(fn (array $item): bool => count(
                array_intersect($userRoles, $item['roles'])
            ) > 0)
            ->values();

        return view('dashboard', [
            'user' => $user,
            'primaryRole' => $user->roles->sortBy('sort_order')->first(),
            'shortcuts' => $shortcuts,
        ]);
    }
}
