<?php

namespace App\Services;

use App\Models\AssignmentLetter;
use App\Models\GeneratedPdf;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;

/**
 * Penggambar PDF Surat Tugas untuk tiga keluarga skema.
 *
 * Struktur ketiganya sama; yang berbeda hanya bahasa, judul, label blok, dan
 * kalimat tujuan — semuanya diambil dari config/assignment_letter.php. Karena
 * itu tiga method render di bawah cukup menyusun primitif yang sama.
 *
 * Berkasnya digambar dengan SimplePdf, sejalan dengan pencetak lain di aplikasi
 * ini yang sengaja tidak memakai pustaka PDF pihak ketiga.
 */
class AssignmentLetterPdfService
{
    use Concerns\DrawsGisLetterhead;
    use Concerns\DrawsSignatures;

    private const MARGIN = 42;

    public function __construct(
        private readonly AssignmentLetterService $letters,
        private readonly AuditLogger $audit,
    ) {}

    public function generate(AssignmentLetter $letter, ?int $userId = null): GeneratedPdf
    {
        $letter->loadMissing(['application.scheme', 'signer']);

        $data = $this->snapshot($letter);
        $pdf = new SimplePdf(self::MARGIN);

        match ($letter->number_family) {
            'lspro' => $this->renderLspro($pdf, $data),
            'ispo' => $this->renderIspo($pdf, $data),
            default => $this->renderLssm($pdf, $data),
        };

        $version = $letter->pdf_version + 1;
        $filename = sprintf(
            'ST_%s_%s_v%d_%s.pdf',
            $letter->stage_code,
            preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($letter->application->order_number ?: $letter->application->uuid)),
            $version,
            now()->format('YmdHis')
        );
        $path = 'generated/assignment-letters/'.$letter->application_id.'/'.$filename;

        Storage::disk('private')->put($path, $pdf->raw());

        $record = GeneratedPdf::create([
            'application_id' => $letter->application_id,
            'document_type' => 'assignment_letter',
            'template_code' => 'assignment_letter_'.$letter->number_family,
            'template_version' => 1,
            'document_version' => $version,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', Storage::disk('private')->get($path)),
            'source_snapshot' => $data,
            'generated_by' => $userId,
        ]);

        $letter->forceFill(['pdf_version' => $version, 'generated_pdf_id' => $record->id])->save();

        $this->audit->log('assignment_letter.generated', $record, [], [
            'application_id' => $letter->application_id,
            'stage' => $letter->stage_code,
            'version' => $version,
        ]);

        return $record;
    }

    /**
     * Seluruh isi surat yang sudah diselesaikan, dibekukan ke source_snapshot
     * agar generate ulang bisa dibandingkan dan dilacak.
     *
     * @return array<string, mixed>
     */
    public function snapshot(AssignmentLetter $letter): array
    {
        $config = config('assignment_letter.templates.'.$letter->number_family);
        $overrides = $letter->field_overrides ?? [];

        $overrides['assignment_date'] = $overrides['assignment_date'] ?? '';
        if (trim((string) $overrides['assignment_date']) === '') {
            $overrides['assignment_date'] = $this->letters->assignmentDateText(
                $letter->assignment_start_date,
                $letter->assignment_end_date
            );
        }

        return [
            'family' => $letter->number_family,
            'stage_code' => $letter->stage_code,
            'title' => $config['title'],
            'number_label' => $config['number_label'],
            'letter_number' => (string) $letter->letter_number,
            'place_date' => trim($letter->letter_place.', '.$this->letters->longDate($letter->letter_date), ' ,'),
            'recipient_heading' => $config['recipient_heading'],
            'recipient_subheading' => $config['recipient_subheading'] ?? null,
            'recipient_name' => (string) ($overrides['company_name'] ?? $letter->application->company_name),
            'recipient_address' => (string) ($overrides['company_address'] ?? ''),
            'salutation' => $config['salutation'],
            'intro' => $config['intro'],
            'assign_line' => $config['assign_line'],
            'purpose' => $this->letters->purposeLine($letter->number_family, $letter->stage_code),
            'table_headings' => $config['table_headings'],
            'primary_rows' => $this->rows($config['primary_fields'], $overrides),
            'detail_rows' => $this->rows($config['detail_fields'], $overrides),
            'auditors' => $letter->auditor_rows ?? [],
            'signature_block' => $config['signature_block'],
            'signer_name' => (string) ($letter->signer_name ?: $letter->signer?->name ?: ''),
            'signer_position' => (string) ($letter->signer_position ?: $config['default_signer_position']),
            // Tanda tangan berstempel milik surat ini; bila kosong jatuh ke
            // tanda tangan profil penanda tangan.
            'signature_path' => $letter->signature_path ?: $letter->signer?->signature_path,
            'notes' => (string) $letter->notes,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $overrides
     * @return array<int, array{label: string, value: string}>
     */
    private function rows(array $fields, array $overrides): array
    {
        $rows = [];

        foreach ($fields as $key => $label) {
            $rows[] = ['label' => $label, 'value' => trim((string) ($overrides[$key] ?? ''))];
        }

        return $rows;
    }

    // ------------------------------------------------------------ tata letak

    private function renderLssm(SimplePdf $pdf, array $data): void
    {
        $this->drawLetterhead($pdf);
        $this->drawCentredTitle($pdf, $data['title']);
        $this->drawPlaceDate($pdf, $data['place_date']);
        $this->drawNumberLine($pdf, $data['number_label'], $data['letter_number']);
        $this->drawRecipient($pdf, $data);
        $this->drawParagraph($pdf, $data['salutation']);
        $this->drawParagraph($pdf, $data['intro']);
        $this->drawLabelBlock($pdf, $data['primary_rows']);
        $this->drawParagraph($pdf, $data['assign_line']);
        $this->drawAuditorTable($pdf, $data);
        $this->drawParagraph($pdf, $data['purpose']);
        $this->drawLabelBlock($pdf, $data['detail_rows']);
        $this->drawSignatureBlock($pdf, $data);
    }

    private function renderIspo(SimplePdf $pdf, array $data): void
    {
        // Bentuknya sama dengan LSSM; yang berbeda kalimat tujuan per tahap
        // dan baris "Kepada Yth" yang terpecah dua baris — keduanya sudah
        // ditangani di snapshot dan drawRecipient.
        $this->renderLssm($pdf, $data);
    }

    private function renderLspro(SimplePdf $pdf, array $data): void
    {
        $this->renderLssm($pdf, $data);
    }

    // ------------------------------------------------------------- primitif

    private function pageHeader(): callable
    {
        return function (SimplePdf $pdf): void {
            $this->drawLetterhead($pdf);
        };
    }

    private function drawCentredTitle(SimplePdf $pdf, string $title, float $size = 13): void
    {
        $y = $pdf->y();
        $width = $pdf->textWidth($title, $size);
        $x = self::MARGIN + (($pdf->contentWidth() - $width) / 2);

        $pdf->text($x, $y + $size, $title, $size, true);
        $pdf->line($x, $y + $size + 3, $x + $width, $y + $size + 3, 0.8);
        $pdf->setY($y + $size + 20);
    }

    private function drawPlaceDate(SimplePdf $pdf, string $text): void
    {
        if ($text === '') {
            return;
        }

        $y = $pdf->y();
        $x = self::MARGIN + $pdf->contentWidth() - $pdf->textWidth($text, 10);

        $pdf->text($x, $y + 10, $text, 10);
        $pdf->setY($y + 20);
    }

    private function drawNumberLine(SimplePdf $pdf, string $label, string $number): void
    {
        $y = $pdf->y();
        $pdf->text(self::MARGIN, $y + 10, trim($label.' '.$number), 10);
        $pdf->setY($y + 24);
    }

    private function drawRecipient(SimplePdf $pdf, array $data): void
    {
        $lines = array_filter([
            $data['recipient_heading'],
            $data['recipient_subheading'],
            $data['recipient_name'],
        ], fn ($line) => filled($line));

        foreach ($lines as $line) {
            $pdf->ensureSpace(14, $this->pageHeader());
            $pdf->text(self::MARGIN, $pdf->y() + 10, (string) $line, 10, false);
            $pdf->moveY(13);
        }

        foreach ($pdf->wrappedLines($data['recipient_address'], $pdf->contentWidth() * 0.75, 10) as $line) {
            if ($line === '') {
                continue;
            }
            $pdf->ensureSpace(14, $this->pageHeader());
            $pdf->text(self::MARGIN, $pdf->y() + 10, $line, 10);
            $pdf->moveY(13);
        }

        $pdf->moveY(10);
    }

    private function drawParagraph(SimplePdf $pdf, string $text, float $size = 10): void
    {
        if (trim($text) === '') {
            return;
        }

        foreach ($pdf->wrappedLines($text, $pdf->contentWidth(), $size) as $line) {
            $pdf->ensureSpace(15, $this->pageHeader());
            $pdf->text(self::MARGIN, $pdf->y() + $size, $line, $size);
            $pdf->moveY(14);
        }

        $pdf->moveY(8);
    }

    /**
     * Blok "Label : nilai" tanpa garis kotak, seperti pada surat aslinya.
     *
     * @param  array<int, array{label: string, value: string}>  $rows
     */
    private function drawLabelBlock(SimplePdf $pdf, array $rows, float $labelWidth = 150): void
    {
        $size = 10;
        $valueX = self::MARGIN + $labelWidth + 12;
        $valueWidth = $pdf->contentWidth() - $labelWidth - 12;

        foreach ($rows as $row) {
            $lines = $pdf->wrappedLines($row['value'] !== '' ? $row['value'] : '-', $valueWidth, $size);

            $pdf->ensureSpace(count($lines) * 13 + 4, $this->pageHeader());
            $top = $pdf->y();

            $pdf->text(self::MARGIN, $top + $size, $row['label'], $size);
            $pdf->text(self::MARGIN + $labelWidth, $top + $size, ':', $size);

            foreach ($lines as $i => $line) {
                $pdf->text($valueX, $top + $size + ($i * 13), $line, $size);
            }

            $pdf->setY($top + (count($lines) * 13) + 2);
        }

        $pdf->moveY(10);
    }

    private function drawAuditorTable(SimplePdf $pdf, array $data): void
    {
        $headings = $data['table_headings'];
        $width = $pdf->contentWidth();
        $columns = [38, $width - 38 - 190, 190];
        $rowHeight = 22;

        $pdf->ensureSpace($rowHeight * 2, $this->pageHeader());

        $y = $pdf->y();
        $pdf->fillRect(self::MARGIN, $y, $width, $rowHeight, 0.92);

        $x = self::MARGIN;
        foreach ($headings as $i => $heading) {
            $pdf->cell($x, $y, $columns[$i], $rowHeight, $heading, 9, true, 'center');
            $x += $columns[$i];
        }
        $pdf->setY($y + $rowHeight);

        if ($data['auditors'] === []) {
            $pdf->cell(self::MARGIN, $pdf->y(), $width, $rowHeight, 'Belum ada auditor yang ditugaskan.', 9, false, 'center');
            $pdf->setY($pdf->y() + $rowHeight + 10);

            return;
        }

        foreach ($data['auditors'] as $index => $auditor) {
            $pdf->ensureSpace($rowHeight, $this->pageHeader());
            $rowY = $pdf->y();
            $x = self::MARGIN;

            $cells = [
                (string) ($index + 1),
                (string) ($auditor['name'] ?? '-'),
                (string) ($auditor['position_label'] ?? ''),
            ];

            foreach ($cells as $i => $cell) {
                $pdf->cell($x, $rowY, $columns[$i], $rowHeight, $cell, 9, false, $i === 0 ? 'center' : 'left');
                $x += $columns[$i];
            }

            $pdf->setY($rowY + $rowHeight);
        }

        $pdf->moveY(14);
    }

    /**
     * Blok penutup dan tanda tangan.
     *
     * Rata kiri, mengikuti ketiga template asli: pada berkas .docx paragraf
     * penutupnya memakai perataan bawaan (kiri) dengan indentasi hanya sekitar
     * 180 twips, bukan rata kanan.
     */
    private function drawSignatureBlock(SimplePdf $pdf, array $data): void
    {
        $lines = $data['signature_block'];
        $x = self::MARGIN + 9; // ≈180 twips, seperti indentasi pada template

        // Tanda tangan berstempel lebih lebar daripada tanda tangan polos,
        // jadi kotaknya disediakan lebih besar.
        $needed = (count($lines) * 13) + 90;
        $pdf->ensureSpace($needed, $this->pageHeader());

        $y = $pdf->y() + 8;

        foreach ($lines as $line) {
            $pdf->text($x, $y + 10, (string) $line, 10);
            $y += 13;
        }

        if ($data['signer_position'] !== '') {
            $pdf->text($x, $y + 10, $data['signer_position'], 10);
            $y += 13;
        }

        $this->drawSignature($pdf, $data['signature_path'], $x, $y + 6, 150, 80);
        $y += 86;

        if ($data['signer_name'] !== '') {
            $name = '('.$data['signer_name'].')';
            $pdf->line($x, $y, $x + $pdf->textWidth($name, 10) + 20, $y, 0.6);
            $pdf->text($x, $y + 12, $name, 10, true);
            $y += 18;
        }

        $pdf->setY($y + 10);
    }
}
