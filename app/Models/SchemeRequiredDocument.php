<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeRequiredDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditional_rules' => 'array',
            'allowed_extensions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CertificationScheme::class, 'certification_scheme_id');
    }
}
