<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\OrderNumberSequence;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderNumberService
{
    public function generate(CertificationApplication $application, ?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        $scheme = $application->scheme;

        return DB::transaction(function () use ($scheme, $date): string {
            $sequence = OrderNumberSequence::query()
                ->where('certification_scheme_id', $scheme->id)
                ->where('year', (int) $date->format('Y'))
                ->where('month', 0)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = OrderNumberSequence::create([
                    'certification_scheme_id' => $scheme->id,
                    'year' => (int) $date->format('Y'),
                    'month' => 0,
                    'last_number' => 0,
                    'format_pattern' => '{SEQ}/{PREFIX}/{ROMAN_MONTH}/{YYYY}',
                    'padding' => config('gis.order_padding', 3),
                ]);
            }

            $sequence->increment('last_number');
            $sequence->refresh();
            $number = str_pad((string) $sequence->last_number, $sequence->padding, '0', STR_PAD_LEFT);

            $order = strtr($sequence->format_pattern, [
                '{SEQ}' => $number,
                '{PREFIX}' => $scheme->order_prefix ?: $scheme->code . '-GIS',
                '{ROMAN_MONTH}' => $this->romanMonth((int) $date->format('n')),
                '{MM}' => $date->format('m'),
                '{YYYY}' => $date->format('Y'),
            ]);

            if (CertificationApplication::where('order_number', $order)->exists()) {
                throw new RuntimeException('Nomor order duplikat terdeteksi. Ulangi proses.');
            }

            return $order;
        }, 3);
    }

    private function romanMonth(int $month): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month] ?? '';
    }
}
