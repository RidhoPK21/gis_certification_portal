<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan klien agar template Formulir Wajib GIS dibagikan untuk sebuah
 * permohonan. Selama belum disetujui, item Formulir Wajib GIS pada checklist
 * tetap terkunci dan permohonan tidak dapat dikirim.
 */
class GisFormRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $this->status,
        };
    }
}
