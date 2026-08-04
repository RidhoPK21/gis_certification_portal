<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PortalNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Notifikasi milik klien pemilik permohonan saja.
     *
     * Filter user_id wajib: action_url memuat token akses sertifikat, satu-
     * satunya tempat token asli tersimpan (CertificateLinkService hanya
     * menyimpan hash), sehingga tidak boleh terlihat oleh akun lain.
     *
     * Cabang kedua menangani notifikasi lama yang dibuat sebelum kolom data
     * diisi application_id. where('data->...') dipilih karena whereJsonContains
     * tidak didukung driver SQLite yang dipakai test.
     */
    public function scopeForApplication(
        Builder $query,
        CertificationApplication $application
    ): Builder {
        return $query->where('user_id', $application->client_id)
            ->where(function (Builder $scoped) use ($application): void {
                $scoped->where('data->application_id', $application->id);

                if (filled($application->order_number)) {
                    $scoped->orWhere(fn (Builder $legacy) => $legacy
                        ->whereNull('data')
                        ->where('message', 'like', '%'.$application->order_number.'%'));
                }
            });
    }
}
