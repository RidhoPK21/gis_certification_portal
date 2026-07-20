<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['assigned_date' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
