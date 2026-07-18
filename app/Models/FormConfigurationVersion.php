<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormConfigurationVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CertificationScheme::class, 'certification_scheme_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
