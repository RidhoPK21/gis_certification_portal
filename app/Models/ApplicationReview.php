<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'completed_at' => 'datetime',
            'panelist_ids' => 'array',
            'auditor_competence_codes' => 'array',
            'ispo_data' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReviewFormItem::class);
    }
}
