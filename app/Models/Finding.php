<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function auditStage(): BelongsTo
    {
        return $this->belongsTo(AuditStage::class);
    }
}
