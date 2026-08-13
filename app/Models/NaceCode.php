<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NaceCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function iafCode(): BelongsTo
    {
        return $this->belongsTo(IafCode::class, 'iaf_code_id');
    }

    /**
     * Label untuk dropdown dan cetakan:
     * "23.6 — Industri pembuatan beton, semen dan gips".
     */
    public function label(): string
    {
        return $this->code.' — '.$this->name_id;
    }
}
