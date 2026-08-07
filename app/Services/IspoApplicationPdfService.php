<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\GeneratedPdf;
use App\Models\SchemeField;
use App\Support\SimplePdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Mencetak Form Aplikasi Permohonan Sertifikasi ISPO (FrO.7201/GIS-2.0) dari
 * isian klien.
 *
 * Berbeda dari skema lain yang templatenya diunduh, diisi manual, lalu diunggah
 * kembali, ISPO tidak membagikan template: formulirnya diisi langsung di sistem
 * dan berkas inilah hasil cetaknya. Karena itu tata letaknya harus mengikuti
 * formulir asli — termasuk kotak centang yang benar-benar dicentang sesuai
 * pilihan pemohon, bukan sekadar daftar teks.
 */
class IspoApplicationPdfService
{
    private const FORM_CODE = 'FrO.7201/GIS-2.0';

    /*
     * Warna diambil langsung dari berkas .docx aslinya: judul bagian dan header
     * tabel FrO.7201 memakai latar biru 8FAADC dengan teks hitam. (Hijau 00B050
     * adalah warna formulir tinjauan FrO.7204, bukan formulir aplikasi ini.)
     */
    private const HEADING_BG = '8FAADC';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DynamicFormService $forms,
        private readonly ConditionalRuleEvaluator $conditions,
    ) {
    }

    public function generate(CertificationApplication $application, ?int $userId = null): GeneratedPdf
    {
        $application->loadMissing(['scheme.sections.fields.options', 'values']);
        $scheme = $this->forms->schemeForApplication($application);
        $application->setRelation('scheme', $scheme);

        $values = $this->forms->values($application);

        $pdf = new SimplePdf(34);
        $this->renderHeader($pdf);
        $this->renderTitle($pdf, $application);

        foreach ($this->forms->visibleSections($scheme, $values) as $section) {
            $this->renderSection($pdf, $section, $values, $application);
        }

        $version = ((int) $application->generatedPdfs()
            ->where('document_type', 'ispo_application_form')
            ->max('document_version')) + 1;

        $filename = sprintf(
            'FrO7201_%s_v%d_%s.pdf',
            preg_replace('/[^A-Za-z0-9]+/', '_', $application->order_number ?: $application->uuid),
            $version,
            now()->format('YmdHis')
        );
        $path = 'generated/ispo-applications/'.$application->id.'/'.$filename;
        Storage::disk('private')->put($path, $pdf->raw());

        $record = GeneratedPdf::create([
            'application_id' => $application->id,
            'document_type' => 'ispo_application_form',
            'template_code' => 'ispo_application',
            'template_version' => 1,
            'document_version' => $version,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', Storage::disk('private')->get($path)),
            'source_snapshot' => ['values' => $values],
            'generated_by' => $userId,
        ]);

        $this->audit->log('ispo_application_pdf.generated', $record, [], [
            'application_id' => $application->id,
            'version' => $version,
        ]);

        return $record;
    }

    // ------------------------------------------------------------ kop
    private function renderHeader(SimplePdf $pdf): void
    {
        $x = 34;
        $y = 24;
        $w = $pdf->contentWidth();
        $h = 54;

        $pdf->rect($x, $y, $w, $h, 0.8);
        $pdf->rect($x, $y, 120, $h, 0.8);

        $logo = dirname(__DIR__, 2).'/public/assets/gis-logo-pdf.jpg';
        if (is_file($logo)) {
            $pdf->imageJpeg($logo, $x + 42, $y + 3, 34, 35);
        } else {
            $pdf->text($x + 40, $y + 24, 'GIS', 20, true);
        }
        $pdf->text($x + 8, $y + 48, 'PT. Global Inspeksi Sertifikasi', 6.5, true);

        $pdf->text($x + 128, $y + 15, 'Formulir', 8);
        $pdf->text($x + 128, $y + 32, 'FORM APLIKASI PERMOHONAN SERTIFIKASI ISPO TERPADU', 10, true);
        $pdf->text($x + 128, $y + 45, 'Pekebun / Perusahaan Perkebunan / Industri Hilir / Usaha Bioenergi', 7);
        $pdf->text($x + $w - 78, $y + 48, self::FORM_CODE, 7);

        $pdf->setY($y + $h + 12);
    }

    private function renderTitle(SimplePdf $pdf, CertificationApplication $application): void
    {
        $rows = [
            ['Nama Pemohon', $application->company_name],
            ['No. Order', $application->order_number ?: '-'],
            ['Tanggal Permohonan', optional($application->order_date)->format('d/m/Y') ?: '-'],
        ];

        $x = 34;
        $labelW = 150;
        $valueW = $pdf->contentWidth() - $labelW;

        foreach ($rows as [$label, $value]) {
            $y = $pdf->y();
            $pdf->cell($x, $y, $labelW, 20, $label, 8, false);
            $pdf->cell($x + $labelW, $y, $valueW, 20, (string) ($value ?: '-'), 8, true);
            $pdf->moveY(20);
        }

        $pdf->moveY(10);
    }

    // ------------------------------------------------------------ bagian
    private function renderSection(SimplePdf $pdf, $section, array $values, CertificationApplication $application): void
    {
        $fields = $section->fields
            ->filter(fn (SchemeField $field) => $field->is_active
                && $this->conditions->passes($field->conditional_rules, $values));

        if ($fields->isEmpty()) {
            return;
        }

        $pdf->ensureSpace(56, fn (SimplePdf $p) => $this->renderHeader($p));
        $pdf->colorCell(34, $pdf->y(), $pdf->contentWidth(), 20, $section->title, self::HEADING_BG, '000000', 9.5);
        $pdf->moveY(24);

        foreach ($fields as $field) {
            match ($field->type) {
                'checkbox_group' => $this->renderChoices($pdf, $field, (array) ($values[$field->code] ?? [])),
                'table' => $this->renderFixedTable($pdf, $field, (array) ($values[$field->code] ?? [])),
                'repeatable' => $this->renderRepeatable($pdf, $field, (array) ($values[$field->code] ?? [])),
                'signature_list' => $this->renderSignatures($pdf, $field, (array) ($values[$field->code] ?? []), $application),
                default => $this->renderScalar($pdf, $field, $values[$field->code] ?? null),
            };
        }

        $pdf->moveY(6);
    }

    private function renderScalar(SimplePdf $pdf, SchemeField $field, mixed $value): void
    {
        $text = $this->displayValue($field, $value);
        $x = 34;
        $labelW = 210;
        $valueW = $pdf->contentWidth() - $labelW;

        $lines = max(
            count($pdf->wrappedLines($field->label, $labelW - 8, 8)),
            count($pdf->wrappedLines($text, $valueW - 8, 8))
        );
        $h = max(19, $lines * 10 + 7);

        $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->renderHeader($p));
        $y = $pdf->y();
        $pdf->cell($x, $y, $labelW, $h, $field->label, 8, false);
        $pdf->cell($x + $labelW, $y, $valueW, $h, $text, 8, false);
        $pdf->moveY($h);
    }

    /**
     * Pilihan dicetak sebagai kotak centang seperti formulir aslinya; yang
     * dipilih pemohon diberi tanda centang.
     */
    private function renderChoices(SimplePdf $pdf, SchemeField $field, array $selected): void
    {
        $options = $field->options;
        $x = 34;
        $labelW = 210;
        $valueW = $pdf->contentWidth() - $labelW;
        $lineH = 11;
        $available = $valueW - 26;

        // Label panjang (mis. uraian KBLI) dibungkus, bukan dipotong, supaya
        // teks pilihannya tetap terbaca utuh seperti pada formulir cetak.
        $wrapped = [];
        foreach ($options as $option) {
            $wrapped[] = $pdf->wrappedLines($option->label, $available, 7.5) ?: [''];
        }

        $totalLines = array_sum(array_map('count', $wrapped));
        $h = max(19, ($totalLines * $lineH) + 10);

        $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->renderHeader($p));
        $y = $pdf->y();
        $pdf->cell($x, $y, $labelW, $h, $field->label, 8, false);
        $pdf->rect($x + $labelW, $y, $valueW, $h);

        $cursorY = $y + 12;
        foreach ($options as $index => $option) {
            $boxX = $x + $labelW + 6;
            $pdf->rect($boxX, $cursorY - 7, 8, 8, 0.6);

            if (in_array((string) $option->value, array_map('strval', $selected), true)) {
                $this->drawCheck($pdf, $boxX + 1, $cursorY - 3);
            }

            foreach ($wrapped[$index] as $lineIndex => $line) {
                $pdf->text($boxX + 13, $cursorY + ($lineIndex * $lineH), $line, 7.5);
            }

            $cursorY += count($wrapped[$index]) * $lineH;
        }

        $pdf->moveY($h);
    }

    private function drawCheck(SimplePdf $pdf, float $x, float $y): void
    {
        $pdf->line($x + 1, $y + 1, $x + 3, $y + 4, 1.0);
        $pdf->line($x + 3, $y + 4, $x + 7, $y - 3, 1.0);
    }

    /**
     * Tabel baris tetap: kolom pertama nama baris, sisanya isian pemohon.
     */
    private function renderFixedTable(SimplePdf $pdf, SchemeField $field, array $data): void
    {
        $columns = $field->column_definitions ?? [];
        $rows = $field->row_definitions ?? [];

        if (! $columns || ! $rows) {
            return;
        }

        $this->tableTitle($pdf, $field->label);

        $labelW = 200;
        $cellW = ($pdf->contentWidth() - $labelW) / max(1, count($columns));
        $headers = array_merge([''], array_map(fn ($c) => $c['label'], $columns));
        $widths = array_merge([$labelW], array_fill(0, count($columns), $cellW));

        $this->tableHeader($pdf, $headers, $widths);

        foreach ($rows as $row) {
            $cells = [$row['label']];
            foreach ($columns as $column) {
                $cells[] = $this->columnValue($column, $data[$row['code']][$column['code']] ?? null);
            }
            $this->tableRow($pdf, $cells, $widths, $field->label);
        }

        $pdf->moveY(8);
    }

    /**
     * Tabel yang barisnya diisi pemohon. Bila belum ada isian, tetap dicetak
     * satu baris kosong supaya bagian itu terlihat memang belum diisi.
     */
    private function renderRepeatable(SimplePdf $pdf, SchemeField $field, array $data): void
    {
        $columns = $field->column_definitions ?? [];

        if (! $columns) {
            return;
        }

        $this->tableTitle($pdf, $field->label);

        $numberW = 26;
        $cellW = ($pdf->contentWidth() - $numberW) / max(1, count($columns));
        $headers = array_merge(['No.'], array_map(fn ($c) => $c['label'], $columns));
        $widths = array_merge([$numberW], array_fill(0, count($columns), $cellW));

        $this->tableHeader($pdf, $headers, $widths);

        $rows = array_values(array_filter($data, fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => filled($v))->isNotEmpty()));

        if (! $rows) {
            $this->tableRow($pdf, array_merge(['1'], array_fill(0, count($columns), '-')), $widths, $field->label);
        }

        foreach ($rows as $index => $row) {
            $cells = [(string) ($index + 1)];
            foreach ($columns as $column) {
                $cells[] = $this->columnValue($column, $row[$column['code']] ?? null);
            }
            $this->tableRow($pdf, $cells, $widths, $field->label);
        }

        $pdf->moveY(8);
    }

    private function tableTitle(SimplePdf $pdf, string $title): void
    {
        $pdf->ensureSpace(46, fn (SimplePdf $p) => $this->renderHeader($p));
        $pdf->text(34, $pdf->y() + 10, $title, 8.5, true);
        $pdf->moveY(16);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, float>  $widths
     */
    private function tableHeader(SimplePdf $pdf, array $headers, array $widths): void
    {
        $lines = 1;
        foreach ($headers as $i => $header) {
            $lines = max($lines, count($pdf->wrappedLines($header, $widths[$i] - 6, 6.5)));
        }
        $h = max(20, $lines * 8 + 8);

        $pdf->ensureSpace($h + 20, fn (SimplePdf $p) => $this->renderHeader($p));
        $y = $pdf->y();
        $cursor = 34;
        foreach ($headers as $i => $header) {
            $pdf->colorCell($cursor, $y, $widths[$i], $h, $header, self::HEADING_BG, '000000', 6.5, true, 'center', 3);
            $cursor += $widths[$i];
        }
        $pdf->moveY($h);
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, float>  $widths
     */
    private function tableRow(SimplePdf $pdf, array $cells, array $widths, string $title): void
    {
        $lines = 1;
        foreach ($cells as $i => $cell) {
            $lines = max($lines, count($pdf->wrappedLines($cell, $widths[$i] - 6, 6.5)));
        }
        $h = max(17, $lines * 8 + 7);

        $pdf->ensureSpace($h + 4, function (SimplePdf $p) use ($title): void {
            $this->renderHeader($p);
            $p->text(34, $p->y() + 10, $title.' (lanjutan)', 8.5, true);
            $p->moveY(16);
        });

        $y = $pdf->y();
        $cursor = 34;
        foreach ($cells as $i => $cell) {
            $pdf->cell($cursor, $y, $widths[$i], $h, $cell, 6.5, false, $i === 0 ? 'left' : 'left', 3);
            $cursor += $widths[$i];
        }
        $pdf->moveY($h);
    }

    /**
     * Blok tanda tangan: Tempat/Tanggal - Nama dan Jabatan - Tanda Tangan dan
     * Stempel, persis tiga kolom seperti formulir aslinya.
     */
    private function renderSignatures(SimplePdf $pdf, SchemeField $field, array $rows, CertificationApplication $application): void
    {
        $this->tableTitle($pdf, $field->label);

        $w = $pdf->contentWidth();
        $colW = $w / 3;
        $headers = ['Tempat dan Tanggal', 'Nama dan Jabatan', 'Tanda Tangan dan Stempel'];
        $this->tableHeader($pdf, $headers, [$colW, $colW, $colW]);

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => filled($v))->isNotEmpty()
        ));

        if (! $rows) {
            $rows = [[]];
        }

        foreach ($rows as $row) {
            $h = 64;
            $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->renderHeader($p));
            $y = $pdf->y();

            $place = trim(($row['tempat'] ?? '').(filled($row['tanggal'] ?? null) ? ', '.$this->formatDate($row['tanggal']) : ''));
            $who = trim(($row['nama'] ?? '').(filled($row['jabatan'] ?? null) ? "\n".$row['jabatan'] : ''));

            $pdf->cell(34, $y, $colW, $h, $place ?: '-', 7.5, false, 'left', 4);
            $pdf->cell(34 + $colW, $y, $colW, $h, $who ?: '-', 7.5, false, 'left', 4);
            $pdf->rect(34 + ($colW * 2), $y, $colW, $h);

            $this->drawSignature($pdf, $row['tanda_tangan'] ?? null, 34 + ($colW * 2) + 6, $y + 6, $colW - 12, $h - 12);

            $pdf->moveY($h);
        }

        $pdf->moveY(8);
    }

    /**
     * Menempel gambar tanda tangan. SimplePdf hanya menerima JPEG, sedangkan
     * pemohon boleh mengunggah PNG, jadi PNG dikonversi lebih dulu ke berkas
     * sementara. Gagal diam-diam agar pembuatan PDF tidak pernah terganggu.
     */
    private function drawSignature(SimplePdf $pdf, ?string $relativePath, float $x, float $y, float $maxW, float $maxH): void
    {
        if (! $relativePath) {
            return;
        }

        $absolute = Storage::disk('private')->path($relativePath);
        if (! is_file($absolute)) {
            return;
        }

        $info = @getimagesize($absolute);
        if (! $info || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
            return;
        }

        $temporary = null;
        if (($info[2] ?? null) !== IMAGETYPE_JPEG) {
            $temporary = $this->convertToJpeg($absolute, $info[2] ?? null);
            if (! $temporary) {
                return;
            }
            $absolute = $temporary;
        }

        $scale = min($maxW / (int) $info[0], $maxH / (int) $info[1]);

        try {
            $pdf->imageJpeg($absolute, $x, $y, (int) $info[0] * $scale, (int) $info[1] * $scale);
        } catch (\Throwable $e) {
            // abaikan: baris tanda tangan tetap tercetak kosong
        } finally {
            if ($temporary && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function convertToJpeg(string $absolute, ?int $type): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        $image = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            default => false,
        };

        if (! $image) {
            return null;
        }

        // Latar putih supaya PNG transparan tidak menjadi hitam saat jadi JPEG.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $target = tempnam(sys_get_temp_dir(), 'sig').'.jpg';
        $ok = imagejpeg($canvas, $target, 92);

        return $ok ? $target : null;
    }

    // ------------------------------------------------------------ nilai
    private function columnValue(array $column, mixed $value): string
    {
        if (($column['type'] ?? 'text') === 'select') {
            foreach ($column['options'] ?? [] as $option) {
                if ((string) $option['value'] === (string) $value) {
                    return $option['label'];
                }
            }
        }

        if (($column['type'] ?? '') === 'date' && filled($value)) {
            return $this->formatDate($value);
        }

        return filled($value) ? (string) $value : '-';
    }

    private function displayValue(SchemeField $field, mixed $value): string
    {
        if (is_array($value)) {
            return $value ? implode(', ', array_map('strval', $value)) : '-';
        }

        if (! filled($value)) {
            return '-';
        }

        if ($field->type === 'boolean') {
            return $value === 'yes' ? 'Ya' : 'Tidak';
        }

        if ($field->type === 'select') {
            $match = $field->options->firstWhere('value', (string) $value);
            if ($match) {
                return $match->label;
            }
        }

        if ($field->type === 'date') {
            return $this->formatDate($value);
        }

        return (string) $value;
    }

    private function formatDate(mixed $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
