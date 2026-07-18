<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchemeField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_repeatable' => 'boolean',
            'is_active' => 'boolean',
            'validation_rules' => 'array',
            'conditional_rules' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SchemeSection::class, 'scheme_section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SchemeFieldOption::class)->orderBy('sort_order');
    }
}
