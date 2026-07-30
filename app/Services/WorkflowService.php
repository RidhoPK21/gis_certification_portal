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

    public function publicTimeline(CertificationApplication $application): array
    {
        $groups = [
            ['code' => 'application', 'label' => 'Permohonan', 'rank' => 1],
            ['code' => 'invoice', 'label' => 'Invoice & Pembayaran', 'rank' => 6],
            ['code' => 'stage1', 'label' => 'Audit Tahap 1', 'rank' => 9],
            ['code' => 'stage2', 'label' => 'Audit Tahap 2', 'rank' => 10],
            ['code' => 'qms', 'label' => 'Audit Lapangan / QMS', 'rank' => 11],
            ['code' => 'ca', 'label' => 'Tindakan Koreksi', 'rank' => 12],
            ['code' => 'review', 'label' => 'Review Sertifikat', 'rank' => 14],
            ['code' => 'final', 'label' => 'Sertifikat Final', 'rank' => 15],
            ['code' => 'surveillance', 'label' => 'Surveillance', 'rank' => 17],
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

        foreach ($groups as &$group) {
            $group['state'] = $current > $group['rank']
                ? 'done'
                : ($current === $group['rank'] ? 'current' : 'pending');
            unset($group['rank']);
        }

        return $groups;
    }
}
