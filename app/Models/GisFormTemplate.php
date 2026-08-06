<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template formulir terbitan LS (FrM.9100, FrM.9102, FrM.9104, dst.) yang
 * dibagikan GIS kepada klien setelah permintaan template disetujui.
 */
class GisFormTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CertificationScheme::class, 'certification_scheme_id');
    }

    public function requiredDocument(): BelongsTo
    {
        return $this->belongsTo(SchemeRequiredDocument::class, 'scheme_required_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getSizeLabelAttribute(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024
            ? number_format($kb / 1024, 1, ',', '.') . ' MB'
            : number_format($kb, 0, ',', '.') . ' KB';
    }
}
