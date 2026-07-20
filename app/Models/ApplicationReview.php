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
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReviewFormItem::class);
    }
}
