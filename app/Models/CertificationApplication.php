<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificationApplication extends Model
{
    protected $table = 'applications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'form_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CertificationScheme::class, 'certification_scheme_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ApplicationValue::class, 'application_id');
    }

    public function repeatableRows(): HasMany
    {
        return $this->hasMany(ApplicationRepeatableRow::class, 'application_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ApplicationRevisionItem::class, 'application_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id')->orderBy('action_date');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ApplicationReview::class, 'application_id');
    }

    public function generatedPdfs(): HasMany
    {
        return $this->hasMany(GeneratedPdf::class, 'application_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'application_id');
    }

    public function auditAssignments(): HasMany
    {
        return $this->hasMany(AuditAssignment::class, 'application_id');
    }

    public function auditStages(): HasMany
    {
        return $this->hasMany(AuditStage::class, 'application_id');
    }

    /**
     * Batasi permohonan hanya pada yang benar-benar ditugaskan kepada
     * auditor tersebut, sesuai cakupan tahap (stage_code) penugasannya.
     * Dipakai bersama oleh halaman Audit dan Dashboard agar keduanya
     * tidak pernah berbeda dalam menentukan hak lihat auditor.
     */
    public function scopeAssignedToAuditor(Builder $query, int $auditorId): Builder
    {
        return $query->whereHas(
            'auditAssignments',
            function (Builder $assignmentQuery) use ($auditorId): void {
                $assignmentQuery
                    ->where('auditor_id', $auditorId)
                    ->where('status', 'assigned')
                    ->where(function (Builder $scopeQuery): void {
                        $scopeQuery->where(function (Builder $q): void {
                            $q->where('stage_code', 'all')
                                ->whereIn('applications.status', [
                                    'payment_completed', 'stage_1_audit', 'stage_2_audit',
                                    'qms_audit', 'corrective_action', 'corrective_revision',
                                ]);
                        })
                        ->orWhere(function (Builder $q): void {
                            $q->where('stage_code', 'stage_1')
                                ->whereIn('applications.status', ['payment_completed', 'stage_1_audit']);
                        })
                        ->orWhere(function (Builder $q): void {
                            $q->where('stage_code', 'stage_2')
                                ->whereIn('applications.status', ['payment_completed', 'stage_1_audit', 'stage_2_audit']);
                        })
                        ->orWhere(function (Builder $q): void {
                            $q->where('stage_code', 'qms')
                                ->whereIn('applications.status', ['payment_completed', 'stage_2_audit', 'qms_audit']);
                        })
                        ->orWhere(function (Builder $q): void {
                            $q->where('stage_code', 'corrective_action')
                                ->whereIn('applications.status', ['corrective_action', 'corrective_revision']);
                        });
                    });
            }
        );
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'application_id');
    }

    public function certificateDrafts(): HasMany
    {
        return $this->hasMany(CertificateDraft::class, 'application_id');
    }

    public function certificateFinal(): HasOne
    {
        return $this->hasOne(CertificateFinal::class, 'application_id')->latestOfMany();
    }

    public function surveillanceSchedules(): HasMany
    {
        return $this->hasMany(SurveillanceSchedule::class, 'application_id')->orderBy('cycle');
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(ApplicationWorkflowStep::class, 'application_id');
    }

    public function value(string $code, mixed $default = null): mixed
    {
        $record = $this->relationLoaded('values')
            ? $this->values->firstWhere('field_code', $code)
            : $this->values()->where('field_code', $code)->first();

        return $record?->value_json ?? $record?->value_text ?? $default;
    }

    public function canBeEditedByClient(): bool
    {
        return in_array($this->status, ['draft', 'revision_requested', 'client_revision'], true);
    }

    /*
     * Hanya draft yang belum pernah dikirim yang boleh dihapus klien.
     * Status revisi juga bisa diedit klien, tapi ordernya sudah berjalan
     * di tim GIS sehingga tidak boleh ikut terhapus.
     */
    public function canBeDeletedByClient(): bool
    {
        return $this->status === 'draft' && $this->order_number === null;
    }
}
