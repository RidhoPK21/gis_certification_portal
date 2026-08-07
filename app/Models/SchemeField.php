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
            'column_definitions' => 'array',
            'row_definitions' => 'array',
        ];
    }

    /**
     * Field yang isinya berupa tabel, bukan satu nilai tunggal.
     *
     * 'table'      = baris tetap (row_definitions), pemohon hanya mengisi sel.
     * 'repeatable' = baris ditambah sendiri oleh pemohon.
     */
    public function isTabular(): bool
    {
        return in_array($this->type, ['table', 'repeatable'], true);
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
