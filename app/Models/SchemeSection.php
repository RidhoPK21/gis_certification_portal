<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchemeSection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['conditional_rules' => 'array'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CertificationScheme::class, 'certification_scheme_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SchemeField::class)->orderBy('sort_order');
    }
}
