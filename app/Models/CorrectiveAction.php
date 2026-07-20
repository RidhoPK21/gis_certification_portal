<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrectiveAction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'implementation_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CorrectiveActionFile::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CorrectiveActionReview::class);
    }
}
