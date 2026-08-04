<?php

namespace App\Services;

use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationWorkflowStep;
use App\Models\CertificationApplication;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    private const ALLOWED = [
        'draft' => ['submitted'],
        'submitted' => ['admin_review'],
        'admin_review' => ['revision_requested', 'rejected', 'application_approved', 'technical_review'],
        'technical_review' => ['admin_review'],
        'revision_requested' => ['client_revision'],
        'client_revision' => ['admin_review'],
        'application_approved' => ['invoice_process'],
        'invoice_process' => ['payment_partial', 'payment_completed'],
        'payment_partial' => ['payment_partial', 'payment_completed'],
        'payment_completed' => ['stage_1_audit', 'stage_2_audit', 'qms_audit'],
        'stage_1_audit' => ['stage_2_audit', 'qms_audit'],
        'stage_2_audit' => ['qms_audit'],
        'qms_audit' => ['corrective_action', 'certificate_review'],
        'corrective_action' => ['corrective_revision', 'certificate_review'],
        'corrective_revision' => ['corrective_action'],
        'certificate_review' => ['final_certificate'],
        'final_certificate' => ['completed'],
        'completed' => ['surveillance'],
    ];

    public function allows(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    public function transition(
        CertificationApplication $application,
        string $to,
        string $action,
        ?string $notes = null,
        ?int $userId = null,
        ?\DateTimeInterface $actionDate = null,
        array $metadata = []
    ): CertificationApplication {
        $from = $application->status;

        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Transisi status {$from} ke {$to} tidak diizinkan.",
            ]);
        }

        return DB::transaction(function () use ($application, $from, $to, $action, $notes, $userId, $actionDate, $metadata) {
            $application->update(['status' => $to, 'current_step' => $to, 'updated_by' => $userId]);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status' => $from,
                'to_status' => $to,
                'action' => $action,
                'notes' => $notes,
                'action_date' => $actionDate ?? now(),
                'system_recorded_at' => now(),
                'performed_by' => $userId,
                'metadata' => $metadata ?: null,
            ]);

            return $application->refresh();
        });
    }

    public function initialize(CertificationApplication $application): void
    {
        $template = WorkflowTemplate::where('certification_scheme_id', $application->certification_scheme_id)
            ->where('is_active', true)
            ->with('steps')
            ->latest('version')
            ->first();

        if (! $template) {
            return;
        }

        foreach ($template->steps as $index => $step) {
            ApplicationWorkflowStep::firstOrCreate(
                ['application_id' => $application->id, 'workflow_step_id' => $step->id],
                [
                    'status' => $index === 0 ? 'completed' : ($index === 1 ? 'open' : 'locked'),
                    'opened_at' => $index === 1 ? now() : null,
                    'due_at' => $index === 1 && $step->sla_days ? now()->addDays($step->sla_days) : null,
                ]
            );
        }
    }

    /**
     * Menentukan apakah Stage 1/Stage 2 boleh dilewati untuk skema permohonan
     * ini, beserta alasannya bila tidak.
     *
     * Sumber kebenaran sengaja WorkflowTemplate, bukan baris
     * application_workflow_steps: order yang di-submit sebelum WorkflowSeeder
     * dijalankan tidak punya baris tersebut, dan kalau dipakai sebagai acuan
     * jawabannya akan salah ("tidak boleh") untuk semua skema.
     *
     * @return array<string, array{allowed: bool, reason: ?string}>
     */
    public function stageSkipInfo(CertificationApplication $application): array
    {
        $template = WorkflowTemplate::where('certification_scheme_id', $application->certification_scheme_id)
            ->where('is_active', true)
            ->with('steps')
            ->latest('version')
            ->first();

        $flags = $template
            ? $template->steps->whereIn('code', ['stage_1', 'stage_2'])->pluck('is_skippable', 'code')
            : collect();

        $categoryLabel = match ($application->scheme?->category) {
            'management_system' => 'sistem manajemen',
            'food_safety' => 'keamanan pangan',
            'ispo' => 'ISPO',
            'product' => 'produk (LSPro)',
            default => $application->scheme?->short_name ?? 'ini',
        };

        $info = [];

        foreach (['stage_1' => 'Stage 1', 'stage_2' => 'Stage 2'] as $code => $label) {
            $allowed = (bool) ($flags[$code] ?? false);

            $info[$code] = [
                'allowed' => $allowed,
                'reason' => $allowed
                    ? null
                    : $label.' wajib dilaksanakan untuk skema '.$categoryLabel.' sehingga tidak dapat dilewati.',
            ];
        }

        return $info;
    }

    /**
     * Ringkasan tahapan untuk halaman publik: label tahap, statusnya, serta
     * tanggal masuk dan tanggal selesai tiap tahap.
     *
     * Tanggal diambil dari application_status_history karena hanya tabel itu
     * yang menyimpan kapan sebuah status benar-benar terjadi (kolom
     * action_date), bukan sekadar kapan barisnya ditulis sistem.
     */
    public function publicTimeline(CertificationApplication $application): array
    {
        $groups = [
            ['code' => 'application', 'label' => 'Permohonan', 'rank' => 1, 'statuses' => [
                'submitted', 'admin_review', 'technical_review', 'revision_requested',
                'client_revision', 'rejected', 'application_approved',
            ]],
            ['code' => 'invoice', 'label' => 'Invoice & Pembayaran', 'rank' => 6, 'statuses' => [
                'invoice_process', 'payment_partial', 'payment_completed',
            ]],
            ['code' => 'stage1', 'label' => 'Audit Tahap 1', 'rank' => 9, 'statuses' => ['stage_1_audit']],
            ['code' => 'stage2', 'label' => 'Audit Tahap 2', 'rank' => 10, 'statuses' => ['stage_2_audit']],
            ['code' => 'qms', 'label' => 'Audit Lapangan / QMS', 'rank' => 11, 'statuses' => ['qms_audit']],
            ['code' => 'ca', 'label' => 'Tindakan Koreksi', 'rank' => 12, 'statuses' => [
                'corrective_action', 'corrective_revision',
            ]],
            ['code' => 'review', 'label' => 'Review Sertifikat', 'rank' => 14, 'statuses' => ['certificate_review']],
            ['code' => 'final', 'label' => 'Sertifikat Final', 'rank' => 15, 'statuses' => [
                'final_certificate', 'completed',
            ]],
            ['code' => 'surveillance', 'label' => 'Surveillance', 'rank' => 17, 'statuses' => ['surveillance']],
        ];

        $rank = [
            'draft' => 0, 'submitted' => 1, 'admin_review' => 2, 'technical_review' => 2, 'revision_requested' => 3,
            'client_revision' => 3, 'rejected' => 4, 'application_approved' => 5,
            'invoice_process' => 6, 'payment_partial' => 7, 'payment_completed' => 8,
            'stage_1_audit' => 9, 'stage_2_audit' => 10, 'qms_audit' => 11,
            'corrective_action' => 12, 'corrective_revision' => 13,
            'certificate_review' => 14, 'final_certificate' => 15, 'completed' => 16,
            'surveillance' => 17,
        ];

        $current = $rank[$application->status] ?? 0;
        $startedAt = $this->timelineStartDates($application, $groups);

        foreach ($groups as $index => &$group) {
            /*
             * Tahap dianggap "sedang berjalan" selama status permohonan berada
             * di antara rank tahap ini dan rank tahap berikutnya. Tanpa rentang
             * ini status seperti admin_review (rank 2) tidak akan pernah cocok
             * dengan rank tahap mana pun sehingga tidak ada tahap yang aktif.
             */
            $next = $groups[$index + 1]['rank'] ?? PHP_INT_MAX;

            $group['state'] = $current >= $next
                ? 'done'
                : ($current >= $group['rank'] ? 'current' : 'pending');

            $group['started_at'] = optional($startedAt[$group['code']] ?? null)->format('d M Y, H:i');

            // Tanggal selesai = saat permohonan pindah ke tahap berikutnya yang
            // benar-benar tercatat, jadi tahap yang dilewati tidak ikut mengklaim tanggal.
            $group['finished_at'] = null;

            if ($group['state'] === 'done') {
                foreach (array_slice($groups, $index + 1) as $later) {
                    if (isset($startedAt[$later['code']])) {
                        $group['finished_at'] = $startedAt[$later['code']]->format('d M Y, H:i');
                        break;
                    }
                }
            }

            unset($group['rank'], $group['statuses']);
        }

        return $groups;
    }

    /**
     * Tanggal paling awal permohonan memasuki tiap tahap.
     *
     * @param  array<int, array{code: string, statuses: array<int, string>}>  $groups
     * @return array<string, \Illuminate\Support\Carbon>
     */
    private function timelineStartDates(CertificationApplication $application, array $groups): array
    {
        $groupOfStatus = [];

        foreach ($groups as $group) {
            foreach ($group['statuses'] as $status) {
                $groupOfStatus[$status] = $group['code'];
            }
        }

        $history = $application->relationLoaded('statusHistory')
            ? $application->statusHistory
            : $application->statusHistory()->get();

        $dates = [];

        foreach ($history as $row) {
            $code = $groupOfStatus[$row->to_status] ?? null;

            if ($code === null || $row->action_date === null) {
                continue;
            }

            if (! isset($dates[$code]) || $row->action_date->lt($dates[$code])) {
                $dates[$code] = $row->action_date;
            }
        }

        // Permohonan lama bisa saja tidak punya baris riwayat "submitted";
        // kolom submitted_at tetap menjadi acuan agar tahap pertama bertanggal.
        if (! isset($dates['application']) && $application->submitted_at) {
            $dates['application'] = $application->submitted_at;
        }

        return $dates;
    }
}
