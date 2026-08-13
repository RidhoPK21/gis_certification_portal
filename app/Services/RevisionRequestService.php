<?php

namespace App\Services;

use App\Models\ApplicationRevisionItem;
use App\Models\CertificationApplication;
use App\Models\User;

/**
 * Permintaan revisi spesifik ke klien.
 *
 * Admin Permohonan meminta revisi dari tahap admin_review (kelengkapan
 * berkas), Tim Teknis dari tahap technical_review (substansi). Prosesnya sama
 * persis, jadi logikanya hidup di sini agar kedua controller tidak menyalin
 * blok yang sama beserta notifikasi dan jejak auditnya.
 *
 * Catatan alur: apa pun yang meminta, perbaikan klien selalu kembali ke
 * admin_review — lihat ApplicationSubmissionService. Tim Teknis menerima
 * kembali permohonan setelah Admin meneruskannya lagi.
 */
class RevisionRequestService
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly PortalNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Aturan validasi bersama untuk form permintaan revisi.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.type' => ['required', 'in:field,document'],
            'targets.*.code' => ['required', 'string'],
            'targets.*.label' => ['required', 'string'],
            'targets.*.note' => ['required', 'string'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'targets.required' => 'Pilih minimal satu item (kolom atau dokumen) yang perlu direvisi dengan mencentang kotak revisi.',
            'targets.min' => 'Pilih minimal satu item (kolom atau dokumen) yang perlu direvisi dengan mencentang kotak revisi.',
            'targets.*.note.required' => 'Catatan revisi wajib diisi untuk setiap item yang dipilih.',
        ];
    }

    /**
     * Buat satu putaran revisi baru dan pindahkan permohonan ke revision_requested.
     *
     * @param  array<int, array{type: string, code: string, label: string, note: string}>  $targets
     * @return int nomor putaran revisi yang baru dibuat
     */
    public function request(
        CertificationApplication $application,
        array $targets,
        ?string $dueDate,
        User $actor,
        string $action,
        string $requesterLabel,
    ): int {
        $round = ((int) $application->revisions()->max('revision_round')) + 1;

        foreach ($targets as $target) {
            ApplicationRevisionItem::create([
                'application_id' => $application->id,
                'revision_round' => $round,
                'target_type' => $target['type'],
                'target_code' => $target['code'],
                'target_label' => $target['label'],
                'revision_note' => $target['note'],
                'due_date' => $dueDate,
                'requested_by' => $actor->id,
            ]);
        }

        $this->workflow->transition(
            $application,
            'revision_requested',
            $action,
            $requesterLabel.' meminta revisi spesifik pada '.count($targets).' item.',
            $actor->id,
            null,
            ['round' => $round]
        );

        $this->notifications->send(
            $application->client_id,
            'revision_requested',
            'Perbaikan permohonan diperlukan',
            'Tim GIS meminta perbaikan pada '.count($targets).' item untuk order '.$application->order_number.'.',
            route('client.applications.edit', $application),
            ['round' => $round]
        );

        $this->audit->log('application.revision_requested', $application, [], ['round' => $round, 'targets' => $targets]);

        return $round;
    }

    public function resolve(ApplicationRevisionItem $revision, string $resolutionNote, User $actor): void
    {
        $old = $revision->toArray();

        $revision->update([
            'status' => 'resolved',
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ]);

        $this->audit->log(
            'application.revision_resolved',
            $revision,
            $old,
            $revision->fresh()->toArray(),
            ['resolution_note' => $resolutionNote]
        );
    }
}
