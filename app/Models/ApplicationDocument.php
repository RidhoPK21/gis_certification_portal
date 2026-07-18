<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ApplicationDocumentVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ApplicationDocumentVersion::class)->where('is_current', true)->latestOfMany();
    }
}
