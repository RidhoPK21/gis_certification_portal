<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Mencetak Tinjauan Permohonan Sertifikasi ISPO (FrO.7204/GIS-3).
 *
 * Tata letaknya mengikuti formulir asli: judul bagian berlatar hijau dengan
 * teks putih, dan hasil kajian dicetak sebagai kotak bercentang pada kolom
 * Cukup / Belum Cukup — bukan pilihan bercoret seperti formulir LSSM.
 */
class IspoReviewPdfService
{
    public function __construct(
        private readonly IspoReviewService $ispo,
        private readonly DynamicFormService $forms,
    ) {
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function render(SimplePdf $pdf, CertificationApplication $application, array $snapshot): void
    {
        $meta = config('review.ispo.form');
        $values = $this->forms->values($application);

        $admin = $application->reviews->where('review_type', 'administration')->sortByDesc('id')->first();
        $technical = $application->reviews->where('review_type', 'technical')->sortByDesc('id')->first();

        $this->header($pdf, $meta);

        $this->sectionIdentity($pdf, $meta, $application, $values, $admin);
        $this->sectionScope($pdf, $meta, $values);
        $this->sectionDocuments($pdf, $meta, $application, $admin);
        $this->sectionRows($pdf, $meta, '4. Kesesuaian Data Formulir Aplikasi',
            config('review.ispo.data_conformity'), 'ispo_data_conformity',
            config('review.ispo.result_options'), $technical,
            'Bagian ini tidak mengulang seluruh data aplikasi; reviewer hanya memastikan data lengkap, konsisten, dan sesuai ruang lingkup yang dimohon.');
        $this->sectionRows($pdf, $meta, '4.1 Kelengkapan Bagian Khusus Sesuai Ruang Lingkup',
            config('review.ispo.section_completeness'), 'ispo_section_completeness',
            config('review.ispo.completeness_options'), $technical);
        $this->sectionRows($pdf, $meta, '5. Kaji Ulang Kemampuan LS ISPO',
            config('review.ispo.capability_review'), 'ispo_capability',
            config('review.ispo.capability_options'), $technical);
        $this->sectionMandays($pdf, $meta, $technical);
        $this->sectionAssignment($pdf, $meta, $snapshot);
        $this->sectionDecision($pdf, $meta, $admin, $technical);
    }

    // ------------------------------------------------------------------ kop
    private function header(SimplePdf $pdf, array $meta): void
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
        $pdf->text($x + 6, $y + 48, 'PT. Global Inspeksi Sertifikasi', 6.5, true);

        $pdf->text($x + 128, $y + 15, 'Formulir', 8);
        $pdf->text($x + 128, $y + 32, $meta['title'], 11, true);
        $pdf->text($x + 128, $y + 45, $meta['subtitle'], 6.5);
        $pdf->text($x + $w - 74, $y + 48, $meta['code'], 7);

        $pdf->setY($y + $h + 12);
    }

    private function heading(SimplePdf $pdf, array $meta, string $title): void
    {
        $pdf->ensureSpace(58, fn (SimplePdf $p) => $this->header($p, $meta));
        $pdf->colorCell(34, $pdf->y(), $pdf->contentWidth(), 20, $title,
            $meta['heading_bg'], $meta['heading_text'], 9.5);
        $pdf->moveY(24);
    }

    private function note(SimplePdf $pdf, string $text): void
    {
        $pdf->ensureSpace(24);
        $pdf->paragraph($text, 7, 9.5);
        $pdf->moveY(4);
    }

    // ------------------------------------------------------------ bagian 1
    private function sectionIdentity(SimplePdf $pdf, array $meta, CertificationApplication $application, array $values, mixed $admin): void
    {
        $this->heading($pdf, $meta, '1. IDENTITAS PERMOHONAN');

        $data = $admin?->ispo_data ?? [];

        $rows = [
            ['Hari/Tanggal', now()->format('d/m/Y'), 'No. Order', $application->order_number ?: '-'],
            ['Tanggal Order', optional($application->order_date)->format('d/m/Y') ?: '-', 'Nama Pemohon/PIC', $values['pic_name'] ?? '-'],
            ['Nama Perusahaan/Koperasi/Gapoktan/Poktan', $application->company_name, 'No. Kontak/E-mail', trim(($values['phone'] ?? '').' / '.($values['email'] ?? '')) ?: '-'],
            ['Alamat Kantor/Kelembagaan', $values['office_address'] ?? '-', 'Alamat Pabrik/Unit Usaha', $values['plant_address'] ?? $values['site_address'] ?? '-'],
            ['Luas Kebun/Kapasitas Produksi', $this->capacityText($values), 'Ruang Lingkup Sertifikasi', $this->scopeText($values)],
            ['Acuan Form Aplikasi', 'FrO.7201/GIS-2.0', 'Tanggal Dokumen Diterima', $this->dateText($data['documents_received_at'] ?? null)],
        ];

        $w = $pdf->contentWidth();
        $labelW = $w * 0.22;
        $valueW = ($w / 2) - $labelW;

        foreach ($rows as [$l1, $v1, $l2, $v2]) {
            $lines = 1;
            foreach ([[$l1, $labelW], [$v1, $valueW], [$l2, $labelW], [$v2, $valueW]] as [$t, $cw]) {
                $lines = max($lines, count($pdf->wrappedLines((string) $t, $cw - 8, 7)));
            }
            $h = max(19, $lines * 9 + 8);

            $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->header($p, $meta));
            $y = $pdf->y();
            $pdf->cell(34, $y, $labelW, $h, $l1, 7, false, 'left', 3);
            $pdf->cell(34 + $labelW, $y, $valueW, $h, (string) $v1, 7, false, 'left', 3);
            $pdf->cell(34 + $labelW + $valueW, $y, $labelW, $h, $l2, 7, false, 'left', 3);
            $pdf->cell(34 + ($labelW * 2) + $valueW, $y, $valueW, $h, (string) $v2, 7, false, 'left', 3);
            $pdf->moveY($h);
        }

        // Verifikasi kelengkapan awal — dicetak sebagai kotak bercentang.
        $completeness = $data['initial_completeness'] ?? null;
        $y = $pdf->y();
        $pdf->ensureSpace(24, fn (SimplePdf $p) => $this->header($p, $meta));
        $pdf->cell(34, $y, $labelW, 20, 'Verifikasi Kelengkapan Awal', 7, false, 'left', 3);
        $pdf->rect(34 + $labelW, $y, $w - $labelW, 20);
        $this->checkboxRow($pdf, 34 + $labelW + 6, $y + 13, [
            'lengkap' => 'Lengkap',
            'perlu_dilengkapi' => 'Perlu dilengkapi',
        ], $completeness);
        $pdf->moveY(20);

        $notes = $data['administrative_notes'] ?? null;
        $this->fullRow($pdf, $meta, 'Catatan Administratif', $notes);
        $pdf->moveY(8);
    }

    // ------------------------------------------------------------ bagian 2
    private function sectionScope(SimplePdf $pdf, array $meta, array $values): void
    {
        $this->heading($pdf, $meta, '2. RUANG LINGKUP DAN JENIS PERMOHONAN');

        $scopes = (array) ($values['applicant_type'] ?? []);
        $applicationTypes = (array) ($values['application_type'] ?? []);

        $w = $pdf->contentWidth();
        $widths = [$w * 0.20, $w * 0.30, $w * 0.35, $w * 0.15];
        $this->tableHeader($pdf, $meta, ['Ruang Lingkup', 'Detail/Subruang Lingkup', 'Jenis Permohonan', 'Keterangan'], $widths);

        $rows = [
            ['Pekebun', ['pekebun_perorangan' => 'Perorangan', 'kelompok_pekebun' => 'Kelompok'], $scopes, ['pekebun_perorangan', 'kelompok_pekebun']],
            ['Perusahaan Perkebunan', ['budi_daya' => 'Budi Daya', 'pks' => 'PKS', 'integrasi' => 'Integrasi'], (array) ($values['upstream_scope'] ?? []), ['perusahaan_perkebunan']],
            ['Industri Hilir', [], (array) ($values['downstream_kbli'] ?? []), ['industri_hilir']],
            ['Usaha Bioenergi', ['bbn' => 'BBN', 'biomassa' => 'Biomassa', 'biogas' => 'Biogas'], (array) ($values['bioenergy_scope'] ?? []), ['perusahaan_bioenergi']],
        ];

        foreach ($rows as [$label, $detailOptions, $detailSelected, $scopeKeys]) {
            $applies = (bool) array_intersect($scopeKeys, $scopes);
            $h = 30;

            $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->header($p, $meta));
            $y = $pdf->y();

            $pdf->cell(34, $y, $widths[0], $h, $label, 7, false, 'left', 3);

            $detailX = 34 + $widths[0];
            $pdf->rect($detailX, $y, $widths[1], $h);
            if ($detailOptions) {
                $this->checkboxRow($pdf, $detailX + 5, $y + 12, $detailOptions, $detailSelected, 6.5, $widths[1] - 10);
            } else {
                // Industri Hilir memakai KBLI, bukan daftar pilihan tetap.
                $pdf->text($detailX + 5, $y + 12, 'KBLI: '.($detailSelected ? implode(', ', $detailSelected) : '__________'), 6.5);
            }

            $typeX = $detailX + $widths[1];
            $pdf->rect($typeX, $y, $widths[2], $h);
            $this->checkboxRow($pdf, $typeX + 5, $y + 12, config('review.ispo.decision_scope_types') ?? [
                'awal' => 'Awal', 'ulang' => 'Ulang', 'transfer' => 'Transfer',
                'perubahan_perluasan' => 'Perubahan/Perluasan', 'perubahan_sertifikat' => 'Perubahan Sertifikat',
            ], $applies ? $applicationTypes : [], 6.5, $widths[2] - 10);

            $pdf->cell($typeX + $widths[2], $y, $widths[3], $h, $applies ? 'Ditinjau' : '-', 7, false, 'center', 3);
            $pdf->moveY($h);
        }

        $this->note($pdf, 'Petunjuk: pilih ruang lingkup dan jenis permohonan sesuai Form Aplikasi. Bagian checklist ditinjau hanya untuk ruang lingkup yang dipilih.');
    }

    // ------------------------------------------------------------ bagian 3
    private function sectionDocuments(SimplePdf $pdf, array $meta, CertificationApplication $application, mixed $admin): void
    {
        $this->heading($pdf, $meta, '3. CHECKLIST DOKUMEN PERMOHONAN');
        $this->note($pdf, 'Hasil kajian diisi dengan tanda centang pada kolom Cukup atau Belum Cukup. Baris yang tidak berlaku bagi permohonan ditandai Belum Cukup beserta alasannya. Keterangan dapat memuat nomor dokumen, masa berlaku, kekurangan, atau tindak lanjut.');

        $saved = $this->itemMap($admin);

        foreach ($this->ispo->documentGroups($application) as $group) {
            $rows = array_map(fn ($row) => [
                'code' => $group['code'].'.'.$row['code'],
                'label' => $row['label'],
            ], $group['rows']);

            $this->resultTable($pdf, $meta, $group['title'], $rows, 'ispo_document',
                config('review.ispo.result_options'), $saved, true);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function sectionRows(SimplePdf $pdf, array $meta, string $title, array $rows, string $type, array $options, mixed $review, ?string $note = null): void
    {
        $this->heading($pdf, $meta, $title);

        if ($note) {
            $this->note($pdf, $note);
        }

        $this->resultTable($pdf, $meta, '', $rows, $type, $options, $this->itemMap($review), false);
    }

    // ------------------------------------------------------------ bagian 6
    private function sectionMandays(SimplePdf $pdf, array $meta, mixed $technical): void
    {
        $this->heading($pdf, $meta, '6. PENETAPAN MANDAYS AUDIT');

        $data = $technical?->ispo_data['mandays'] ?? [];
        $w = $pdf->contentWidth();
        $widths = [$w * 0.30, $w * 0.13, $w * 0.13, $w * 0.13, $w * 0.31];

        $this->tableHeader($pdf, $meta, ['Sektor/Subruang Lingkup', 'Tahap 1 (HOK)', 'Tahap 2 (HOK)', 'Total (HOK)', 'Dasar/Keterangan'], $widths);

        foreach (config('review.ispo.mandays_sectors') as $sector) {
            $row = $data[$sector['code']] ?? [];
            $stage1 = $row['stage_1'] ?? null;
            $stage2 = $row['stage_2'] ?? null;
            // Total dihitung, bukan diketik ulang, supaya tidak pernah berselisih
            // dengan penjumlahan tahap 1 dan tahap 2.
            $total = (filled($stage1) || filled($stage2))
                ? (string) ((float) ($stage1 ?: 0) + (float) ($stage2 ?: 0))
                : '-';

            $cells = [
                $sector['label'],
                filled($stage1) ? (string) $stage1 : '-',
                filled($stage2) ? (string) $stage2 : '-',
                $total,
                $row['note'] ?? '-',
            ];
            $this->tableRow($pdf, $meta, $cells, $widths);
        }

        $pdf->moveY(8);
    }

    // ------------------------------------------------------------ bagian 7
    private function sectionAssignment(SimplePdf $pdf, array $meta, array $snapshot): void
    {
        $this->heading($pdf, $meta, '7. PENUGASAN TIM AUDIT DAN PANELIS');

        $auditors = $snapshot['assigned_auditors'] ?? [];
        $panelists = $snapshot['assigned_panelists'] ?? [];

        $w = $pdf->contentWidth();
        $widths = [$w * 0.06, $w * 0.44, $w * 0.06, $w * 0.44];
        $this->tableHeader($pdf, $meta, ['No.', 'Tim Auditor yang Ditugaskan (LA, A, TA)', 'No.', 'Panelis/Reviewer/Pengambil Keputusan'], $widths);

        for ($i = 0; $i < max(4, max(count($auditors), count($panelists))); $i++) {
            $this->tableRow($pdf, $meta, [
                (string) ($i + 1),
                $auditors[$i] ?? '-',
                (string) ($i + 1),
                $panelists[$i] ?? '-',
            ], $widths);
        }

        $pdf->moveY(8);
    }

    // ------------------------------------------------------------ bagian 8
    private function sectionDecision(SimplePdf $pdf, array $meta, mixed $admin, mixed $technical): void
    {
        $this->heading($pdf, $meta, '8. HASIL TINJAUAN PERMOHONAN');

        $data = $technical?->ispo_data ?? [];
        $notes = trim((string) ($admin?->notes ?? '').' '.(string) ($technical?->notes ?? ''));

        $this->fullRow($pdf, $meta, 'Catatan Belum Memenuhi', $notes ?: null);

        // Keputusan akhir mengikuti hasil tahap teknis (Approval).
        $decision = match ($technical?->status) {
            'approved', 'accepted' => 'approved',
            'rejected' => 'rejected',
            default => $data['decision'] ?? null,
        };

        $w = $pdf->contentWidth();
        $labelW = $w * 0.30;
        $y = $pdf->y();
        $pdf->ensureSpace(26, fn (SimplePdf $p) => $this->header($p, $meta));
        $pdf->cell(34, $y, $labelW, 22, 'Hasil Tinjauan Permohonan', 7.5, true, 'left', 4);
        $pdf->rect(34 + $labelW, $y, $w - $labelW, 22);
        $this->checkboxRow($pdf, 34 + $labelW + 6, $y + 14, config('review.ispo.decision_options'), $decision, 7);
        $pdf->moveY(22);

        $this->fullRow($pdf, $meta, 'Alasan jika dikembalikan/ditolak', $technical?->rejection_reason ?? $admin?->rejection_reason);
        $this->fullRow($pdf, $meta, 'Tindak lanjut/dokumen yang harus dilengkapi', $data['follow_up'] ?? null);
        $this->fullRow($pdf, $meta, 'Tanggal permintaan kelengkapan', $this->dateText($data['completion_requested_at'] ?? null), 18);
        $this->fullRow($pdf, $meta, 'Batas waktu kelengkapan', $this->dateText($data['completion_due_at'] ?? null), 18);
        $this->fullRow($pdf, $meta, 'Tanggal dokumen diterima kembali', $this->dateText($data['documents_returned_at'] ?? null), 18);

        $y = $pdf->y();
        $pdf->ensureSpace(24, fn (SimplePdf $p) => $this->header($p, $meta));
        $pdf->cell(34, $y, $labelW, 20, 'Hasil verifikasi ulang', 7.5, false, 'left', 4);
        $pdf->rect(34 + $labelW, $y, $w - $labelW, 20);
        $this->checkboxRow($pdf, 34 + $labelW + 6, $y + 13, config('review.ispo.reverification_options'), $data['reverification'] ?? null, 7);
        $pdf->moveY(28);

        // Dua kotak tanda tangan: Peninjau diisi Admin, Approval diisi Teknis.
        $this->signatureBoxes($pdf, $meta, $admin, $technical);

        $this->note($pdf, 'Catatan: permohonan hanya dapat diterima apabila dokumen wajib lengkap, data aplikasi konsisten, dan LS ISPO memiliki ruang lingkup akreditasi serta sumber daya yang sesuai.');
    }

    private function signatureBoxes(SimplePdf $pdf, array $meta, mixed $admin, mixed $technical): void
    {
        $w = $pdf->contentWidth();
        $colW = $w / 2;
        $h = 96;

        $pdf->ensureSpace($h + 26, fn (SimplePdf $p) => $this->header($p, $meta));
        $y = $pdf->y();

        $pdf->colorCell(34, $y, $colW, 20, 'Peninjau / Application Reviewer', $meta['heading_bg'], $meta['heading_text'], 8, true, 'center');
        $pdf->colorCell(34 + $colW, $y, $colW, 20, 'Approval', $meta['heading_bg'], $meta['heading_text'], 8, true, 'center');
        $pdf->moveY(20);

        $y = $pdf->y();
        foreach ([[34, $admin], [34 + $colW, $technical]] as [$x, $review]) {
            $pdf->rect($x, $y, $colW, $h);

            $date = $review?->action_date
                ? Carbon::parse($review->action_date)->timezone(config('app.timezone'))->format('d/m/Y')
                : '____________________';
            $pdf->text($x + 8, $y + 16, 'Tanggal: '.$date, 7.5);

            $signature = $review?->reviewed_by
                ? optional(User::find($review->reviewed_by))->signature_path
                : null;
            $this->drawSignature($pdf, $signature, $x + 10, $y + 24, $colW - 20, 44);

            $pdf->line($x + 10, $y + $h - 18, $x + $colW - 10, $y + $h - 18, 0.4);
            $pdf->text($x + 10, $y + $h - 6, '('.($review?->signed_name ?: '..........................................').')', 7.5);
        }

        $pdf->moveY($h + 10);
    }

    // ------------------------------------------------------------ pembantu
    /**
     * Tabel hasil kajian: satu kolom per pilihan, dicentang sesuai hasilnya.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string>  $options
     * @param  array<string, array{status: string, notes: ?string}>  $saved
     */
    private function resultTable(SimplePdf $pdf, array $meta, string $title, array $rows, string $type, array $options, array $saved, bool $numbered): void
    {
        if (! $rows) {
            return;
        }

        if ($title !== '') {
            $pdf->ensureSpace(40, fn (SimplePdf $p) => $this->header($p, $meta));
            $pdf->text(34, $pdf->y() + 10, $title, 8, true);
            $pdf->moveY(15);
        }

        $w = $pdf->contentWidth();
        $optionW = 52;
        $numberW = $numbered ? 24 : 0;
        $labelW = $w - $numberW - ($optionW * count($options)) - 120;

        $headers = $numbered ? ['No.', 'Dokumen/Data'] : ['Hal yang Ditinjau'];
        $widths = $numbered ? [$numberW, $labelW] : [$labelW + $numberW];
        foreach ($options as $label) {
            $headers[] = $label;
            $widths[] = $optionW;
        }
        $headers[] = 'Keterangan';
        $widths[] = 120;

        $this->tableHeader($pdf, $meta, $headers, $widths);

        foreach ($rows as $index => $row) {
            $item = $saved[$type.'.'.$row['code']] ?? null;
            $status = $item['status'] ?? null;

            $lines = max(
                count($pdf->wrappedLines($row['label'], $labelW - 6, 6.5)),
                count($pdf->wrappedLines((string) ($item['notes'] ?? ''), 114, 6.5))
            );
            $h = max(18, $lines * 8 + 8);

            $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->header($p, $meta));
            $y = $pdf->y();
            $cursor = 34;

            if ($numbered) {
                $pdf->cell($cursor, $y, $numberW, $h, (string) ($index + 1), 6.5, false, 'center', 2);
                $cursor += $numberW;
            }

            $pdf->cell($cursor, $y, $numbered ? $labelW : $labelW + $numberW, $h, $row['label'], 6.5, false, 'left', 3);
            $cursor += $numbered ? $labelW : $labelW + $numberW;

            foreach (array_keys($options) as $optionKey) {
                $pdf->rect($cursor, $y, $optionW, $h);
                if ($status === $optionKey) {
                    $this->drawCheck($pdf, $cursor + ($optionW / 2) - 4, $y + ($h / 2) + 2);
                }
                $cursor += $optionW;
            }

            $pdf->cell($cursor, $y, 120, $h, (string) ($item['notes'] ?? '-'), 6.5, false, 'left', 3);
            $pdf->moveY($h);
        }

        $pdf->moveY(8);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, float>  $widths
     */
    private function tableHeader(SimplePdf $pdf, array $meta, array $headers, array $widths): void
    {
        $lines = 1;
        foreach ($headers as $i => $header) {
            $lines = max($lines, count($pdf->wrappedLines($header, $widths[$i] - 6, 6.5)));
        }
        $h = max(20, $lines * 8 + 8);

        $pdf->ensureSpace($h + 22, fn (SimplePdf $p) => $this->header($p, $meta));
        $y = $pdf->y();
        $cursor = 34;
        foreach ($headers as $i => $header) {
            $pdf->colorCell($cursor, $y, $widths[$i], $h, $header, $meta['heading_bg'], $meta['heading_text'], 6.5, true, 'center', 3);
            $cursor += $widths[$i];
        }
        $pdf->moveY($h);
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, float>  $widths
     */
    private function tableRow(SimplePdf $pdf, array $meta, array $cells, array $widths): void
    {
        $lines = 1;
        foreach ($cells as $i => $cell) {
            $lines = max($lines, count($pdf->wrappedLines((string) $cell, $widths[$i] - 6, 6.5)));
        }
        $h = max(17, $lines * 8 + 7);

        $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->header($p, $meta));
        $y = $pdf->y();
        $cursor = 34;
        foreach ($cells as $i => $cell) {
            $pdf->cell($cursor, $y, $widths[$i], $h, (string) $cell, 6.5, false, 'left', 3);
            $cursor += $widths[$i];
        }
        $pdf->moveY($h);
    }

    private function fullRow(SimplePdf $pdf, array $meta, string $label, ?string $value, float $minHeight = 34): void
    {
        $w = $pdf->contentWidth();
        $labelW = $w * 0.30;
        $text = filled($value) ? $value : '-';
        $lines = count($pdf->wrappedLines($text, $w - $labelW - 8, 7.5));
        $h = max($minHeight, $lines * 10 + 8);

        $pdf->ensureSpace($h + 4, fn (SimplePdf $p) => $this->header($p, $meta));
        $y = $pdf->y();
        $pdf->cell(34, $y, $labelW, $h, $label, 7.5, false, 'left', 4);
        $pdf->cell(34 + $labelW, $y, $w - $labelW, $h, $text, 7.5, false, 'left', 4);
        $pdf->moveY($h);
    }

    /**
     * Deretan kotak centang mendatar; yang terpilih diberi tanda centang.
     *
     * @param  array<string, string>  $options
     */
    private function checkboxRow(SimplePdf $pdf, float $x, float $baseline, array $options, mixed $selected, float $size = 7, ?float $maxWidth = null): void
    {
        $selected = is_array($selected) ? array_map('strval', $selected) : [(string) $selected];
        $cursorX = $x;
        $cursorY = $baseline;
        $startX = $x;

        foreach ($options as $key => $label) {
            $itemWidth = 12 + $pdf->textWidth($label, $size) + 8;

            // Membungkus ke baris berikutnya bila melewati lebar sel.
            if ($maxWidth !== null && ($cursorX - $startX) + $itemWidth > $maxWidth && $cursorX > $startX) {
                $cursorX = $startX;
                $cursorY += 10;
            }

            $pdf->rect($cursorX, $cursorY - 6, 7, 7, 0.6);
            if (in_array((string) $key, $selected, true)) {
                $this->drawCheck($pdf, $cursorX + 0.5, $cursorY - 2.5);
            }
            $pdf->text($cursorX + 11, $cursorY, $label, $size);
            $cursorX += $itemWidth;
        }
    }

    private function drawCheck(SimplePdf $pdf, float $x, float $y): void
    {
        $pdf->line($x + 1, $y + 1, $x + 2.5, $y + 3.5, 1.0);
        $pdf->line($x + 2.5, $y + 3.5, $x + 6, $y - 2.5, 1.0);
    }

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

        $scale = min($maxW / (int) $info[0], $maxH / (int) $info[1]);

        try {
            $pdf->imageJpeg($absolute, $x, $y, (int) $info[0] * $scale, (int) $info[1] * $scale);
        } catch (\Throwable $e) {
            // abaikan: nama ketik tetap tercetak
        }
    }

    /**
     * @return array<string, array{status: string, notes: ?string}>
     */
    private function itemMap(mixed $review): array
    {
        if (! $review) {
            return [];
        }

        return $review->items
            ->mapWithKeys(fn ($item) => [
                $item->item_type.'.'.$item->item_code => [
                    'status' => $item->review_status,
                    'notes' => $item->notes,
                ],
            ])
            ->all();
    }

    private function capacityText(array $values): string
    {
        $parts = array_filter([
            filled($values['total_area_submitted'] ?? null) ? $values['total_area_submitted'].' Ha' : null,
            filled($values['installed_capacity'] ?? null) ? $values['installed_capacity'].' ton/tahun' : null,
            $values['plant_capacity'] ?? null,
        ]);

        return $parts ? implode(' | ', $parts) : '-';
    }

    private function scopeText(array $values): string
    {
        $labels = [
            'pekebun_perorangan' => 'Pekebun Perorangan',
            'kelompok_pekebun' => 'Kelompok Pekebun',
            'perusahaan_perkebunan' => 'Perusahaan Perkebunan',
            'industri_hilir' => 'Industri Hilir',
            'perusahaan_bioenergi' => 'Usaha Bioenergi',
        ];

        $selected = array_map(
            fn ($key) => $labels[$key] ?? $key,
            (array) ($values['applicant_type'] ?? [])
        );

        return $selected ? implode(', ', $selected) : '-';
    }

    private function dateText(mixed $value): string
    {
        if (! filled($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
