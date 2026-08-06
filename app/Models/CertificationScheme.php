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

    public function gisFormTemplates(): HasMany
    {
        return $this->hasMany(GisFormTemplate::class)->orderBy('sort_order');
    }

    public function getColorToneAttribute(): string
    {
        return match (strtoupper((string) $this->code)) {
            'ISO9001' => 'blue',
            'ISO14001' => 'green',
            'ISO27001' => 'indigo',
            'ISO20000' => 'teal',
            'ISO37001' => 'rose',
            'ISO37301' => 'amber',
            'ISO22000' => 'green',
            'HACCP' => 'orange',
            'SNI', 'SNI/LSPRO', 'LSPRO' => 'slate',
            'ISPO_PEK' => 'emerald',
            'ISPO_COMP' => 'forest',
            default => 'blue',
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return 'badge badge-scheme badge-scheme-' . $this->color_tone;
    }

    public function getAccentColorAttribute(): string
    {
        return match (strtoupper((string) $this->code)) {
            'ISO9001' => '#0284c7',
            'ISO14001' => '#15803d',
            'ISO27001' => '#4f46e5',
            'ISO20000' => '#0d9488',
            'ISO37001' => '#e11d48',
            'ISO37301' => '#d97706',
            'ISO22000' => '#16a34a',
            'HACCP' => '#ea580c',
            'SNI', 'SNI/LSPRO', 'LSPRO' => '#475569',
            'ISPO_PEK' => '#059669',
            'ISPO_COMP' => '#15803d',
            default => '#0284c7',
        };
    }
}
