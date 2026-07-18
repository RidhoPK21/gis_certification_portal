<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificationScheme extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SchemeSection::class)->orderBy('sort_order');
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(SchemeRequiredDocument::class)->orderBy('sort_order');
    }

    public function formVersions(): HasMany
    {
        return $this->hasMany(FormConfigurationVersion::class);
    }
}
