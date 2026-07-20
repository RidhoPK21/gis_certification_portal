<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditStageFile extends Model
{
    protected $guarded = [];

    public function auditStage(): BelongsTo
    {
        return $this->belongsTo(AuditStage::class);
    }
}
