<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IafCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function naceCodes(): HasMany
    {
        return $this->hasMany(NaceCode::class)->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Hanya kode NACE yang masih aktif.
     *
     * Sengaja relasi tersendiri, bukan menyaring di dalam naceCodes(): halaman
     * Superadmin tetap perlu melihat yang nonaktif untuk mengaktifkannya lagi,
     * sedangkan form klien tidak boleh menawarkannya.
     */
    public function activeNaceCodes(): HasMany
    {
        return $this->naceCodes()->where('is_active', true);
    }

    /**
     * Label untuk dropdown dan cetakan: "16 — Beton, semen, kapur, plester, dll".
     */
    public function label(): string
    {
        return $this->code.' — '.$this->name_id;
    }
}
