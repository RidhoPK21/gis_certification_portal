<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STAGES = [
        'belum_lunas' => 'Belum Lunas',
        'tahap_1' => 'Pembayaran Tahap 1',
        'tahap_2' => 'Pembayaran Tahap 2',
        'tahap_3' => 'Pembayaran Tahap 3',
        'lunas' => 'Sudah Lunas',
    ];

    protected $guarded = [];

    public function stageLabel(): string
    {
        return self::STAGES[$this->payment_stage] ?? $this->payment_stage;
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CertificationApplication::class, 'application_id');
    }
}
