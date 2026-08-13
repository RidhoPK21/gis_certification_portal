<?php

namespace App\Services\Concerns;

use App\Support\SimplePdf;

/**
 * Kop surat GIS untuk dokumen berbentuk surat.
 *
 * Berbeda dari kop formulir tinjauan (ReviewPdfService::renderHeader) yang
 * berupa kotak berisi kode formulir: surat memakai logo di kiri, nama lembaga
 * di tengah, lalu garis pemisah — mengikuti template .docx Surat Tugas.
 */
trait DrawsGisLetterhead
{
    protected function letterheadLogoPath(): ?string
    {
        $logo = dirname(__DIR__, 3).'/public/assets/gis-logo-pdf.jpg';

        return is_file($logo) ? $logo : null;
    }

    protected function drawLetterhead(SimplePdf $pdf, float $top = 28): void
    {
        $left = 42;
        $width = $pdf->contentWidth();

        $logo = $this->letterheadLogoPath();
        if ($logo) {
            $pdf->imageJpeg($logo, $left, $top, 58, 58);
        } else {
            $pdf->text($left + 8, $top + 36, 'GIS', 22, true);
        }

        $company = config('assignment_letter.letterhead.company');
        $tagline = config('assignment_letter.letterhead.tagline');

        $companyX = $left + ((($width - $left) - $pdf->textWidth($company, 14)) / 2) + 20;
        $taglineX = $left + ((($width - $left) - $pdf->textWidth($tagline, 9)) / 2) + 20;

        // Warna mengikuti berkas .docx asli: nama lembaga biru tua, tagline merah.
        $pdf->text($companyX, $top + 28, $company, 14, true, config('assignment_letter.letterhead.company_color'));
        $pdf->text($taglineX, $top + 44, $tagline, 9, false, config('assignment_letter.letterhead.tagline_color'));

        // Garis ganda seperti pada kop asli.
        $ruleY = $top + 62;
        $rule = config('assignment_letter.letterhead.rule_color');
        $pdf->line($left, $ruleY, $left + $width, $ruleY, 1.4, $rule);
        $pdf->line($left, $ruleY + 3, $left + $width, $ruleY + 3, 0.6, $rule);

        $pdf->setY($ruleY + 18);
    }
}
