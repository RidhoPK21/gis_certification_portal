<?php

namespace App\Services\Concerns;

/**
 * Bulan dalam angka Romawi, dipakai pada nomor order maupun nomor Surat Tugas
 * (mis. 023/LSPr-ST/GIS/MT/II/2026).
 */
trait FormatsRomanMonth
{
    protected function romanMonth(int $month): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month] ?? '';
    }
}
