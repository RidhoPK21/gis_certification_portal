<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveillanceSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'scheduled_date' => 'date',
            'actual_date' => 'date',
            'calculation_snapshot' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function certificateFinal(): BelongsTo
    {
        return $this->belongsTo(CertificateFinal::class);
    }
}
