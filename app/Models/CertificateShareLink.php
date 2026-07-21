<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateShareLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'password_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CertificateDraft::class, 'certificate_draft_id');
    }

    public function final(): BelongsTo
    {
        return $this->belongsTo(CertificateFinal::class, 'certificate_final_id');
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && ! $this->revoked_at
            && $this->expires_at?->isFuture()
            && (! $this->max_access || $this->access_count < $this->max_access);
    }
}
