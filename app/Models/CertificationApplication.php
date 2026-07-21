<?php

namespace App\Models;

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
}
