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

        $role = (string) ($user->roles->sortBy('sort_order')->pluck('code')->first() ?? 'client');
        $statuses = match ($role) {
            'admin_application' => ['submitted', 'admin_review', 'revision_requested', 'application_approved'],
            'finance' => ['application_approved', 'invoice_process', 'payment_partial', 'payment_completed'],
            'auditor' => ['stage_1_audit', 'stage_2_audit', 'qms_audit', 'corrective_action', 'corrective_revision'],
            'technical' => ['certificate_review', 'final_certificate', 'completed'],
            default => [],
        };

        $query = CertificationApplication::query();

        return view('internal.dashboard', [
            'role' => $role,
            'stats' => [
                'all' => $query->count(),
                'queue' => $statuses ? (clone $query)->whereIn('status', $statuses)->count() : $query->count(),
                'overdue' => 0,
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ],
            'applications' => $statuses
                ? $query->whereIn('status', $statuses)->with(['scheme', 'client'])->latest()->limit(10)->get()
                : $query->with(['scheme', 'client'])->latest()->limit(10)->get(),
        ]);
    }
}
