<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditStage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'audit_date' => 'date',
            'action_date' => 'date',
            'mandays' => 'decimal:2',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AuditStageFile::class);
    }
}
