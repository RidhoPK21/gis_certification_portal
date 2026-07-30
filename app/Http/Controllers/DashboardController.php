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
            $listQuery = clone $query;
            if ($request->filled('scheme_id')) {
                $listQuery->where('certification_scheme_id', $request->integer('scheme_id'));
            }

            $actionRequiredStatuses = [
                'corrective_action',
                'corrective_revision',
                'revision_requested',
                'client_revision',
                'invoice_process',
                'payment_partial',
                'certificate_review',
                'draft',
            ];

            $allActionApps = (clone $query)
                ->whereIn('status', $actionRequiredStatuses)
                ->with(['scheme', 'invoice', 'findings', 'certificateDrafts'])
                ->get()
                ->sortBy(function ($app) {
                    $priority = match ($app->status) {
                        'corrective_action', 'corrective_revision', 'revision_requested', 'client_revision' => 1,
                        'invoice_process', 'payment_partial' => 2,
                        'certificate_review' => 3,
                        'draft' => 4,
                        default => 5,
                    };
                    return [$priority, -$app->updated_at->getTimestamp()];
                })
                ->values();

            $draftApps = $allActionApps->where('status', '===', 'draft')->values();
            $nonDraftApps = $allActionApps->where('status', '!==', 'draft')->values();

            $panelItems = collect();

            if ($draftApps->count() > 1) {
                foreach ($nonDraftApps->take(3) as $app) {
                    $panelItems->push((object) [
                        'is_summary' => false,
                        'app' => $app,
                        'title' => $app->order_number ?: 'Draft #' . $app->id,
                        'scheme' => $app->scheme,
                        'status' => $app->status,
                        'description' => self::getActionDescription($app->status),
                        'button_text' => self::getActionButtonText($app->status),
                        'button_url' => in_array($app->status, ['draft', 'revision_requested', 'client_revision'], true)
                            ? route('client.applications.edit', $app)
                            : route('client.applications.show', $app),
                    ]);
                }

                if ($nonDraftApps->count() > 3) {
                    $remaining = $nonDraftApps->count() - 3;
                    $panelItems->push((object) [
                        'is_summary' => true,
                        'title' => $remaining . ' permohonan lain memerlukan perhatian',
                        'description' => 'Terdapat tindakan lain yang perlu diselesaikan.',
                        'button_text' => 'Lihat Semua Tindakan',
                        'button_url' => route('client.applications.index'),
                        'count' => $remaining,
                    ]);
                }

                $panelItems->push((object) [
                    'is_summary' => true,
                    'title' => $draftApps->count() . ' draft permohonan belum selesai',
                    'description' => 'Lanjutkan pengisian ketika data sudah siap.',
                    'button_text' => 'Lihat Draft',
                    'button_url' => route('client.applications.index', ['status' => 'draft']),
                    'count' => $draftApps->count(),
                ]);
            } else {
                foreach ($allActionApps->take(3) as $app) {
                    $panelItems->push((object) [
                        'is_summary' => false,
                        'app' => $app,
                        'title' => $app->order_number ?: 'Draft #' . $app->id,
                        'scheme' => $app->scheme,
                        'status' => $app->status,
                        'description' => self::getActionDescription($app->status),
                        'button_text' => self::getActionButtonText($app->status),
                        'button_url' => in_array($app->status, ['draft', 'revision_requested', 'client_revision'], true)
                            ? route('client.applications.edit', $app)
                            : route('client.applications.show', $app),
                    ]);
                }

                if ($allActionApps->count() > 3) {
                    $remaining = $allActionApps->count() - 3;
                    $panelItems->push((object) [
                        'is_summary' => true,
                        'title' => $remaining . ' permohonan lain memerlukan perhatian',
                        'description' => 'Terdapat tindakan lain yang perlu diselesaikan.',
                        'button_text' => 'Lihat Semua Tindakan',
                        'button_url' => route('client.applications.index'),
                        'count' => $remaining,
                    ]);
                }
            }

            return view('client.dashboard', [
                'applications' => $listQuery->with('scheme')->latest()->limit(6)->get(),
                'actionRequiredApps' => $allActionApps,
                'panelItems' => $panelItems,
                'stats' => [
                    'total' => (clone $query)->count(),
                    'waiting' => (clone $query)->whereIn('status', ['submitted', 'admin_review'])->count(),
                    'revision' => $allActionApps->count(),
                    'final' => (clone $query)->whereIn('status', ['final_certificate', 'completed'])->count(),
                ],
                'notifications' => PortalNotification::where('user_id', $user->id)->latest()->limit(5)->get(),
                'schemes' => \App\Models\CertificationScheme::where('is_active', true)->orderBy('sort_order')->get(),
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

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        $listQuery = $statuses
            ? (clone $query)->whereIn('status', $statuses)
            : (clone $query);

        if ($request->filled('scheme_id')) {
            $listQuery->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('internal.dashboard', [
            'role' => $role,
            'stats' => [
                'all' => $query->count(),
                'queue' => $statuses ? (clone $query)->whereIn('status', $statuses)->count() : $query->count(),
                'overdue' => 0,
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ],
            'applications' => $listQuery->with(['scheme', 'client'])->latest()->limit(10)->get(),
            'schemes' => \App\Models\CertificationScheme::orderBy('sort_order')->get(),
        ]);
    }

    private static function getActionButtonText(string $status): string
    {
        return match ($status) {
            'draft' => 'Lanjutkan Draft',
            'revision_requested', 'client_revision' => 'Perbaiki Permohonan',
            'invoice_process', 'payment_partial' => 'Lihat Pembayaran',
            'corrective_action' => 'Isi Tindakan Koreksi',
            'corrective_revision' => 'Perbaiki Tindakan Koreksi',
            'certificate_review' => 'Tinjau Draft',
            default => 'Lihat Detail',
        };
    }

    private static function getActionDescription(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft permohonan belum dikirim untuk pemeriksaan Admin.',
            'revision_requested', 'client_revision' => 'Admin meminta perbaikan data atau dokumen permohonan.',
            'invoice_process' => 'Invoice telah diterbitkan, perlu tindak lanjut pembayaran.',
            'payment_partial' => 'Pembayaran baru sebagian, perlu penyelesaian pelunasan.',
            'corrective_action' => 'Auditor menyampaikan temuan yang memerlukan tindakan koreksi.',
            'corrective_revision' => 'Tindakan koreksi perlu diperbaiki sesuai ulasan auditor.',
            'certificate_review' => 'Draft sertifikat tersedia untuk diperiksa sebelum finalisasi.',
            default => 'Membutuhkan peninjauan atau tindakan Anda.',
        };
    }
}
