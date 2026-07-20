<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedPdf extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }
}
