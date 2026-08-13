<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentLetter extends Model
{
    protected $guarded = [];

    /**
     * Tahap audit yang punya Surat Tugas sendiri.
     *
     * corrective_action sengaja tidak ada di sini: tindakan koreksi adalah
     * kelanjutan audit yang sama, bukan penugasan baru.
     */
    public const STAGES = [
        'stage_1' => 'Audit Tahap 1',
        'stage_2' => 'Audit Tahap 2',
        'qms' => 'Audit Lapangan / QMS',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'assignment_start_date' => 'date',
            'assignment_end_date' => 'date',
            'signature_uploaded_at' => 'datetime',
            'auditor_rows' => 'array',
            'field_overrides' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function generatedPdf(): BelongsTo
    {
        return $this->belongsTo(GeneratedPdf::class, 'generated_pdf_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Surat dianggap terbit hanya bila PDF-nya sudah dibuat; baris yang masih
     * berupa draft tidak membuka gerbang kerja auditor.
     */
    public function isIssued(): bool
    {
        return $this->generated_pdf_id !== null;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage_code] ?? $this->stage_code;
    }
}
