<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\GeneratedPdf;
use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;

class ReviewPdfService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DynamicFormService $forms
    ) {
    }

    public function generate(CertificationApplication $application, ?int $userId = null): GeneratedPdf
    {
        $application->load([
            'scheme.sections.fields', 'scheme.requiredDocuments', 'values',
            'documents.currentVersion', 'reviews.items', 'auditStages', 'findings',
        ]);
        $application->setRelation('scheme', $this->forms->schemeForApplication($application));
        $snapshot = $this->snapshot($application);
        $pdf = new SimplePdf(34);
        if ($application->scheme->review_template === 'ispo') {
            $this->renderIspo($pdf, $snapshot);
        } elseif ($application->scheme->review_template === 'sni') {
            $this->renderSni($pdf, $snapshot);
        } else {
            $this->renderLssm($pdf, $snapshot);
        }

        $version = ((int) $application->generatedPdfs()->where('document_type', 'application_review')->max('document_version')) + 1;
        $filename = sprintf('review_%s_v%d_%s.pdf', preg_replace('/[^A-Za-z0-9]+/', '_', $application->order_number ?: $application->uuid), $version, now()->format('YmdHis'));
        $path = 'generated/reviews/'.$application->id.'/'.$filename;
        Storage::disk('private')->put($path, $pdf->raw());
        $record = GeneratedPdf::create([
            'application_id' => $application->id, 'document_type' => 'application_review',
            'template_code' => $application->scheme->review_template,
            'template_version' => 1, 'document_version' => $version, 'file_path' => $path,
            'checksum_sha256' => hash('sha256', Storage::disk('private')->get($path)),
            'source_snapshot' => $snapshot, 'generated_by' => $userId,
        ]);
        $this->audit->log('review_pdf.generated', $record, [], ['application_id' => $application->id, 'version' => $version]);

        return $record;
    }

    public function snapshot(CertificationApplication $application): array
    {
        $values = $application->values->mapWithKeys(fn ($v) => [$v->field_code => $v->value_json ?? $v->value_text])->all();
        $documents = $application->scheme->requiredDocuments->map(function ($required) use ($application) {
            $uploaded = $application->documents->firstWhere('document_code', $required->code);

            return [
                'code' => $required->code, 'name' => $required->name, 'group' => $required->review_group,
                'presence' => $uploaded?->currentVersion ? 'Ada' : 'Tidak Ada',
                'review_status' => $uploaded?->review_status ?? 'pending',
                'notes' => $uploaded?->review_note,
                'version' => $uploaded?->currentVersion?->version,
            ];
        })->values()->all();
        $adminReview = $application->reviews->where('review_type', 'administration')->sortByDesc('id')->first();
        $technicalReview = $application->reviews->where('review_type', 'technical')->sortByDesc('id')->first();

        return [
            'application' => [
                'id' => $application->id, 'order_number' => $application->order_number ?: '-',
                'order_date' => optional($application->order_date)->format('d/m/Y') ?: '-',
                'review_date' => now()->format('d/m/Y'), 'company_name' => $application->company_name,
                'contact_email' => $application->contact_email, 'status' => $application->status,
            ],
            'scheme' => $application->scheme->only(['code', 'name', 'standard', 'review_template']),
            'values' => $values, 'documents' => $documents,
            'administration_review' => $this->reviewArray($adminReview),
            'technical_review' => $this->reviewArray($technicalReview),
            'audit_stages' => $application->auditStages->toArray(),
        ];
    }

    /**
     * Ubah review menjadi array + lampirkan path tanda tangan milik
     * penanda tangan (reviewed_by) untuk disisipkan ke PDF.
     */
    private function reviewArray(mixed $review): ?array
    {
        if (! $review) {
            return null;
        }

        $data = $review->toArray();
        $data['signature_path'] = $review->reviewed_by
            ? optional(User::find($review->reviewed_by))->signature_path
            : null;

        return $data;
    }

    private function renderHeader(SimplePdf $pdf, string $title, string $code): void
    {
        $x = 34; $y = 28; $w = $pdf->contentWidth(); $h = 68;
        $pdf->rect($x, $y, $w, $h, 0.8);
        $pdf->rect($x, $y, 142, $h, 0.8);
        $logo = dirname(__DIR__, 2).'/public/assets/gis-logo-pdf.jpg';
        if (is_file($logo)) {
            $pdf->imageJpeg($logo, $x + 51, $y + 3, 40, 41);
        } else {
            $pdf->text($x + 49, $y + 27, 'GIS', 22, true);
        }
        $pdf->text($x + 15, $y + 56, 'PT. Global Inspeksi Sertifikasi', 7, true);
        $pdf->text($x + 150, $y + 17, 'Formulir', 8);
        $pdf->text($x + 150, $y + 42, $title, 14, true);
        $pdf->text($x + $w - 72, $y + 61, $code, 7);
        $pdf->setY($y + $h + 16);
    }

    private function infoRows(SimplePdf $pdf, array $rows): void
    {
        $x = 34; $labelW = 148; $valueW = $pdf->contentWidth() - $labelW;
        foreach ($rows as [$label, $value]) {
            $lines = max(count($pdf->wrappedLines((string) $label, $labelW - 8, 8)), count($pdf->wrappedLines((string) $value, $valueW - 8, 8)));
            $h = max(24, $lines * 11 + 7);
            $pdf->ensureSpace($h + 4, fn ($p) => null);
            $y = $pdf->y();
            $pdf->cell($x, $y, $labelW, $h, (string) $label, 8, false);
            $pdf->cell($x + $labelW, $y, $valueW, $h, $this->dash($value), 8, in_array($label, ['Nama Perusahaan', 'Nama Pemohon'], true));
            $pdf->moveY($h);
        }
        $pdf->moveY(10);
    }

    private function reviewTable(SimplePdf $pdf, string $title, array $items): void
    {
        $pdf->ensureSpace(70);
        $pdf->text(34, $pdf->y() + 10, $title, 11, true);
        $pdf->moveY(18);
        $x = 34; $widths = [28, 250, 115, $pdf->contentWidth() - 393];
        $headers = ['No.', 'Dokumen', 'Hasil Kajian', 'Keterangan'];
        $y = $pdf->y();
        $cx = $x;
        foreach ($headers as $i => $header) { $pdf->fillRect($cx, $y, $widths[$i], 25, .92); $pdf->cell($cx, $y, $widths[$i], 25, $header, 8, true, 'center'); $cx += $widths[$i]; }
        $pdf->moveY(25);
        foreach (array_values($items) as $index => $item) {
            $label = $item['name'] ?? $item['item_label'] ?? '-';
            $status = $this->statusText($item['review_status'] ?? 'pending');
            $note = $item['notes'] ?? '';
            $presence = $item['presence'] ?? null;
            $result = $presence ? $presence.' / '.$status : $status;
            $lines = max(count($pdf->wrappedLines($label, $widths[1] - 8, 7.5)), count($pdf->wrappedLines($note ?: '-', $widths[3] - 8, 7.5)));
            $h = max(24, $lines * 10 + 8);
            $pdf->ensureSpace($h + 32, function (SimplePdf $p) use ($title) {
                $p->text(34, $p->y() + 10, $title.' (lanjutan)', 10, true); $p->moveY(18);
            });
            $y = $pdf->y(); $cx = $x;
            foreach ([(string) ($index + 1), $label, $result, $note ?: '-'] as $i => $value) {
                $pdf->cell($cx, $y, $widths[$i], $h, $value, 7.5, false, $i === 0 ? 'center' : 'left'); $cx += $widths[$i];
            }
            $pdf->moveY($h);
        }
        $pdf->moveY(8);
        $pdf->paragraph('Catatan: seluruh dokumen wajib diberikan dan dinyatakan cukup sebelum proses sertifikasi berlanjut ke tahap berikutnya.', 7.5, 10);
    }

    private function decisionBox(SimplePdf $pdf, string $title, ?array $review): void
    {
        $pdf->ensureSpace(160);
        $x = 34; $w = $pdf->contentWidth(); $status = $review['status'] ?? 'in_progress';
        $decision = in_array($status, ['approved', 'accepted'], true) ? 'Diterima' : ($status === 'rejected' ? 'Ditolak' : 'Belum Diputuskan');
        $notes = $review['rejection_reason'] ?? $review['notes'] ?? '-';
        $date = ! empty($review['action_date']) ? date('d/m/Y', strtotime($review['action_date'])) : now()->format('d/m/Y');
        $name = $review['signed_name'] ?? '-';
        $y = $pdf->y();
        $pdf->cell($x, $y, $w - 175, 28, $title, 8, true);
        $pdf->cell($x + $w - 175, $y, 175, 28, $decision, 9, true, 'center');
        $pdf->moveY(28); $y = $pdf->y();

        $boxH = 108; $rx = $x + $w - 175; $rw = 175;
        $pdf->cell($x, $y, $w - 175, $boxH, 'Alasan/catatan: '.$this->dash($notes), 8, false);
        // Kolom kanan: tanggal, area tanda tangan (gambar), garis, lalu nama.
        $pdf->rect($rx, $y, $rw, $boxH);
        $pdf->text($rx + 6, $y + 16, "Tanggal: {$date}", 8, true);
        $pdf->text($rx + 6, $y + 30, 'Nama dan tanda tangan:', 8, true);
        $this->drawSignature($pdf, $review['signature_path'] ?? null, $rx + 8, $y + 36, $rw - 16, 46);
        $pdf->line($rx + 6, $y + $boxH - 18, $rx + $rw - 6, $y + $boxH - 18, 0.4);
        $pdf->text($rx + 6, $y + $boxH - 6, $name, 8, true);
        $pdf->moveY($boxH + 12);
    }

    /**
     * Sisipkan gambar tanda tangan (JPEG) di dalam kotak keputusan bila
     * penanda tangan sudah mengunggah e-sign. Ukuran diskalakan mengikuti
     * rasio asli agar pas di dalam area (maks $maxW x $maxH) tanpa menimpa
     * teks. Gagal diam-diam agar pembuatan PDF tidak pernah terganggu.
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

        $scale = min($maxW / (int) $info[0], $maxH / (int) $info[1]);
        $drawW = (int) $info[0] * $scale;
        $drawH = (int) $info[1] * $scale;

        try {
            $pdf->imageJpeg($absolute, $x, $y, $drawW, $drawH);
        } catch (\Throwable $e) {
            // abaikan: tetap tampil nama ketik saja
        }
    }

    private function renderLssm(SimplePdf $pdf, array $s): void
    {
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI LSSM', 'FrM.9101/GIS-7');
        $v = $s['values']; $a = $s['application'];
        $this->infoRows($pdf, [
            ['Hari/tanggal', $a['review_date'].'   |   No Order: '.$a['order_number'].'   |   Tanggal Order: '.$a['order_date']],
            ['Nama Perusahaan', $a['company_name']], ['Alamat Perusahaan', $v['company_address'] ?? $v['head_office_address'] ?? '-'],
            ['Lingkup Industri', $v['industry_scope'] ?? $v['business_sector'] ?? '-'], ['IAF Code', $v['iaf_code'] ?? '-'],
            ['Lingkup Sertifikasi dan batasan', $v['certification_scope'] ?? '-'], ['Standar Audit', $s['scheme']['standard'] ?? '-'],
            ['Jenis Sertifikasi', $v['certification_type'] ?? '-'], ['Area Audit', $v['site_type'] ?? '-'], ['Jenis Audit', $v['audit_type'] ?? '-'],
        ]);
        $admin = array_values(array_filter($s['documents'], fn ($d) => ($d['group'] ?? 'administration') === 'administration'));
        $technical = array_values(array_filter($s['documents'], fn ($d) => ($d['group'] ?? 'administration') !== 'administration'));
        $this->reviewTable($pdf, 'I. Administrasi', $admin);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian administrasi', $s['administration_review']);
        $pdf->addPage();
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI LSSM', 'FrM.9101/GIS-7');
        $this->reviewTable($pdf, 'II. Teknis', $technical ?: $admin);
        $this->infoRows($pdf, [
            ['Kesesuaian ruang lingkup dengan LSSM', $v['scope_review_result'] ?? '-'],
            ['Kemampuan LSSM melakukan audit', $v['audit_capability'] ?? '-'],
            ['Mandays Audit (Stage 1 + Stage 2)', $v['audit_mandays'] ?? '-'],
            ['Kompetensi auditor yang diperlukan', $v['required_auditor_competence'] ?? '-'],
            ['Kompetensi spesifik', $v['specific_auditor_competence'] ?? '-'],
            ['Tim Auditor (LA, A, TA)', $v['assigned_auditor_team'] ?? '-'],
            ['Panelis yang ditugaskan', $v['assigned_panelists'] ?? '-'],
        ]);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian teknis', $s['technical_review']);
    }

    private function renderIspo(SimplePdf $pdf, array $s): void
    {
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI ISPO', 'FrO.7201/GIS-1.1');
        $v = $s['values']; $a = $s['application'];
        $this->infoRows($pdf, [
            ['Hari/tanggal', $a['review_date'].'   |   No Order: '.$a['order_number'].'   |   Tanggal Order: '.$a['order_date']],
            ['Nama Perusahaan', $a['company_name']],
            ['Alamat Perusahaan/Alamat Kebun dan Pabrik', $v['company_address'] ?? $v['organization_address'] ?? '-'],
            ['Jenis Sertifikasi', $v['certification_scope_type'] ?? $v['certification_type'] ?? '-'],
            ['Kelas Perkebunan', $v['plantation_class'] ?? '-'], ['Luas Kebun', ($v['core_land_area'] ?? $v['total_area'] ?? '-').' Ha'],
            ['Jenis Audit', $v['audit_type'] ?? $v['certification_type'] ?? '-'],
        ]);
        $this->reviewTable($pdf, 'Dokumen', $s['documents']);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian administrasi', $s['administration_review']);
        $pdf->addPage();
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI ISPO', 'FrO.7201/GIS-1.1');
        $this->infoRows($pdf, [
            ['Mandays Audit', $v['audit_mandays'] ?? 'Ditentukan berdasarkan jenis usaha dan kompleksitas ruang lingkup'],
            ['Tim Auditor yang ditugaskan (LA, A, TA)', $v['assigned_auditor_team'] ?? '-'],
            ['Panelis yang ditugaskan', $v['assigned_panelists'] ?? '-'],
        ]);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian teknis', $s['technical_review']);
    }

    private function renderSni(SimplePdf $pdf, array $s): void
    {
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI S-5', 'Fr.7201/GIS-4');
        $v = $s['values']; $a = $s['application'];
        $this->infoRows($pdf, [
            ['Hari/tanggal', $a['review_date'].'   |   No Order: '.$a['order_number'].'   |   Tanggal Order: '.$a['order_date']],
            ['Nama & alamat pemohon', $a['company_name'].' - '.($v['company_address'] ?? '-')],
            ['Produk', $v['product_name'] ?? '-'], ['Tipe/Kategori', $v['product_category'] ?? '-'],
            ['Nomor Standar SNI', $v['sni_number'] ?? '-'], ['Merek', $v['brand'] ?? '-'],
            ['Nama dan alamat shipper', trim(($v['shipper_name'] ?? '').' '.($v['shipper_address'] ?? '')) ?: '-'],
            ['Nama dan alamat pengambilan contoh', trim(($v['producer_name'] ?? '').' '.($v['sampling_address'] ?? '')) ?: '-'],
        ]);
        $this->reviewTable($pdf, 'Checklist Dokumen Permohonan', $s['documents']);
        $this->decisionBox($pdf, 'Kesimpulan Tinjauan Permohonan', $s['administration_review']);
        $pdf->ensureSpace(85);
        $this->infoRows($pdf, [
            ['Pihak LSPro - Pengoleksi', $v['collector_name'] ?? '-'],
            ['Pihak LSPro - Peninjau', $v['reviewer_name'] ?? '-'],
            ['Pihak Klien', $v['client_signatory_name'] ?? $a['company_name']],
        ]);
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            'sufficient', 'meets', 'approved', 'accepted' => 'Cukup / Memenuhi',
            'insufficient', 'not_meets', 'revision' => 'Belum Cukup',
            'rejected' => 'Tidak Memenuhi',
            default => 'Belum Dikaji',
        };
    }

    private function dash(mixed $value): string
    {
        return filled($value) ? (string) $value : '-';
    }
}
