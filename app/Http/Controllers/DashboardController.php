<?php

namespace App\Http\Controllers;

use App\Models\CertificationApplication;
use App\Models\PortalNotification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard per-role.
     *
     * Klien mendapat dashboard permohonan. Role internal untuk sementara
     * memakai dashboard pintasan (dashboard internal penuh dibangun Fase 4+).
     */
    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing('roles');

        if ($user->hasRole('client')) {
            $query = CertificationApplication::where('client_id', $user->id);

            return view('client.dashboard', [
                'applications' => (clone $query)->with('scheme')->latest()->limit(6)->get(),
                'stats' => [
                    'total' => (clone $query)->count(),
                    'waiting' => (clone $query)->whereIn('status', ['submitted', 'admin_review'])->count(),
                    'revision' => (clone $query)->whereIn('status', ['revision_requested', 'client_revision', 'corrective_revision'])->count(),
                    'final' => (clone $query)->whereIn('status', ['final_certificate', 'completed'])->count(),
                ],
                'notifications' => PortalNotification::where('user_id', $user->id)->latest()->limit(5)->get(),
            ]);
        }

        $userRoles = $user->roles->pluck('code')->all();

        $shortcuts = collect(config('navigation'))
            ->reject(fn (array $item): bool => $item['route'] === 'dashboard')
            ->filter(fn (array $item): bool => count(array_intersect($userRoles, $item['roles'])) > 0)
            ->values();

        return view('dashboard', [
            'user' => $user,
            'primaryRole' => $user->roles->sortBy('sort_order')->first(),
            'shortcuts' => $shortcuts,
        ]);
    }
}
