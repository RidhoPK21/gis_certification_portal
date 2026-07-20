<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveActionFile extends Model
{
    protected $guarded = [];

    public function correctiveAction(): BelongsTo
    {
        return $this->belongsTo(CorrectiveAction::class);
    }
}
