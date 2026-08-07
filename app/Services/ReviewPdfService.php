<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\GeneratedPdf;
use App\Models\User;
use App\Services\Concerns\DrawsSignatures;
use App\Services\ReviewService;
use App\Support\SimplePdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReviewPdfService
{
    use DrawsSignatures;

    /**
     * Identitas formulir tinjauan (judul kop, kode, nama lembaga) untuk sebuah
     * skema. Tata letaknya sama, hanya teksnya yang berbeda antar skema.
     *
     * @return array{title: string, code: string, body: string, mandays_text: string, extra_identity?: array<int, array{label: string, field: string}>}
     */
    private function formMeta(array $snapshot): array
    {
        $schemeCode = $snapshot['scheme']['code'] ?? '';

        return array_merge(
            config('review.form_meta_default'),
            config('review.form_meta.'.$schemeCode, [])
        );
    }

    /**
     * Judul dan kode kop yang sedang dipakai, supaya halaman lanjutan yang
     * terbentuk otomatis tetap berkop seperti halaman pertama.
     *
     * @var array{0: string, 1: string}|null
     */
    private ?array $currentHeader = null;

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
            'auditAssignments.auditor',
        ]);
        $application->setRelation('scheme', $this->forms->schemeForApplication($application));

        /*
         * Tanda tangan diambil saat PDF dibuat, lalu jejaknya dicatat pada
         * review agar terlihat tanda tangan siapa yang tercetak pada versi ini.
         */
        foreach ($application->reviews as $review) {
            $signature = $review->reviewed_by ? optional(User::find($review->reviewed_by))->signature_path : null;

            if ($signature && $review->signature_path !== $signature) {
                $review->forceFill(['signature_path' => $signature])->save();
            }
        }

        $snapshot = $this->snapshot($application);
        $pdf = new SimplePdf(34);
        if ($application->scheme->review_template === 'ispo') {
            // FrO.7204 tata letaknya jauh berbeda dari formulir LSSM (kolom
            // bercentang, bukan pilihan bercoret), jadi dirender terpisah.
            app(IspoReviewPdfService::class)->render($pdf, $application, $snapshot);
        } elseif ($application->scheme->review_template === 'sni') {
            $this->renderSni($pdf, $snapshot);
        } elseif ($application->scheme->review_template === 'lsml') {
            $this->renderLsml($pdf, $snapshot);
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
        $adminReview = $application->reviews->where('review_type', 'administration')->sortByDesc('id')->first();
        $technicalReview = $application->reviews->where('review_type', 'technical')->sortByDesc('id')->first();

        /*
         * Kolom "Keterangan*)" FrM.9107 tersimpan pada review_form_items, bukan
         * pada dokumen, jadi dipetakan per tahap kajian: item administrasi tidak
         * boleh menimpa keterangan yang diisi Tim Teknis.
         */
        $adminItems = $adminReview?->items->keyBy('item_code') ?? collect();
        $technicalItems = $technicalReview?->items->keyBy('item_code') ?? collect();

        $documents = $application->scheme->requiredDocuments->map(function ($required) use ($application, $adminItems, $technicalItems) {
            $uploaded = $application->documents->firstWhere('document_code', $required->code);

            /*
             * Hasil kajian disimpan per tahap. Dokumen bergrup 'both' dinilai
             * dua kali dan hasilnya tidak boleh saling menimpa, jadi keduanya
             * dibawa terpisah alih-alih memakai satu review_status dokumen.
             */
            $perStage = [];

            foreach (['administration' => $adminItems, 'technical' => $technicalItems] as $stage => $items) {
                $item = $items->get($required->code);

                $perStage[$stage] = [
                    'review_status' => $item?->review_status ?? ($uploaded?->review_status ?? 'pending'),
                    'remark_option' => $item?->remark_option,
                    'remark_date' => $item?->remark_date ? $this->indonesianDate($item->remark_date) : null,
                    'notes' => $item?->notes ?? $uploaded?->review_note,
                ];
            }

            return [
                'code' => $required->code, 'name' => $required->name, 'group' => $required->review_group,
                'presence' => $uploaded?->currentVersion ? 'Ada' : 'Tidak Ada',
                'review_status' => $uploaded?->review_status ?? 'pending',
                'notes' => $uploaded?->review_note,
                'remark_option' => $perStage['administration']['remark_option'] ?? $perStage['technical']['remark_option'],
                'remark_date' => $perStage['administration']['remark_date'] ?? $perStage['technical']['remark_date'],
                'stages' => $perStage,
                'version' => $uploaded?->currentVersion?->version,
            ];
        })->values()->all();

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
            'assigned_auditors' => $this->assignedAuditors($application),
            'assigned_panelists' => $this->panelistNames($technicalReview),
            'rows' => [
                'administration' => $this->stageRows($application, 'administration', $adminItems, $documents),
                'technical' => $this->stageRows($application, 'technical', $technicalItems, $documents),
            ],
        ];
    }

    /**
     * Baris satu tahap tinjauan: label mengikuti formulir, sedangkan hasil
     * kajian dan keterangannya diambil dari review_form_items tahap tersebut.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function stageRows(CertificationApplication $application, string $stage, mixed $items, array $documents): array
    {
        $byCode = collect($documents)->keyBy('code');

        return app(ReviewService::class)->formRows($application, $stage)
            ->map(function ($row) use ($items, $byCode, $stage) {
                $item = $items->get($row->code);
                $document = $byCode->get($row->code);

                return [
                    'code' => $row->code,
                    // Label formulir dipakai apa adanya, bukan nama dokumen checklist.
                    'name' => $row->name,
                    'free_remark' => $row->free_remark,
                    'presence' => $document['presence'] ?? 'Tidak Ada',
                    'stages' => [
                        $stage => [
                            'review_status' => $item?->review_status ?? 'pending',
                            'remark_option' => $item?->remark_option,
                            'remark_date' => $item?->remark_date ? $this->indonesianDate($item->remark_date) : null,
                            'notes' => $item?->notes,
                        ],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Tim auditor diambil dari penugasan auditor, bukan diketik ulang, dan
     * diurutkan LA → A → TA sesuai penulisan pada formulir.
     *
     * @return array<int, string>
     */
    private function assignedAuditors(CertificationApplication $application): array
    {
        $order = ['LA' => 0, 'A' => 1, 'TA' => 2];

        return $application->auditAssignments
            ->where('status', 'assigned')
            ->sortBy([
                fn ($assignment) => $order[$assignment->assignment_role] ?? 9,
                fn ($assignment) => $assignment->auditor?->name ?? '',
            ])
            ->map(fn ($assignment) => trim(($assignment->auditor?->name ?? '-').' ('.$assignment->assignment_role.')'))
            // Satu auditor bisa ditugaskan pada beberapa tahap; cukup ditulis sekali.
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function panelistNames(mixed $technicalReview): array
    {
        $ids = $technicalReview?->panelist_ids ?? [];

        if (! $ids) {
            return [];
        }

        return User::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /**
     * Daftar bernomor bergaya formulir ("1. Nama"), satu nama per baris.
     */
    private function numberedList(array $names): string
    {
        if (! $names) {
            return '-';
        }

        return implode("\n", array_map(
            fn (int $index, string $name) => ($index + 1).'. '.$name,
            array_keys($names),
            $names
        ));
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

    /**
     * Callback pemecah halaman yang mencetak ulang kop formulir, dipakai semua
     * blok agar halaman lanjutan tidak pernah tampil tanpa kop.
     */
    private function pageHeader(?string $continuationTitle = null): callable
    {
        return function (SimplePdf $pdf) use ($continuationTitle): void {
            if ($this->currentHeader) {
                $this->renderHeader($pdf, $this->currentHeader[0], $this->currentHeader[1]);
            }

            if ($continuationTitle) {
                $pdf->text(34, $pdf->y() + 10, $continuationTitle, 10, true);
                $pdf->moveY(18);
            }
        };
    }

    private function renderHeader(SimplePdf $pdf, string $title, string $code): void
    {
        $this->currentHeader = [$title, $code];
        $x = 34; $y = 28; $w = $pdf->contentWidth(); $h = 60;
        $pdf->rect($x, $y, $w, $h, 0.8);
        $pdf->rect($x, $y, 142, $h, 0.8);
        $logo = dirname(__DIR__, 2).'/public/assets/gis-logo-pdf.jpg';
        if (is_file($logo)) {
            $pdf->imageJpeg($logo, $x + 53, $y + 3, 36, 37);
        } else {
            $pdf->text($x + 49, $y + 25, 'GIS', 22, true);
        }
        $pdf->text($x + 15, $y + 52, 'PT. Global Inspeksi Sertifikasi', 7, true);
        $pdf->text($x + 150, $y + 16, 'Formulir', 8);
        $pdf->text($x + 150, $y + 38, $title, 13, true);
        $pdf->text($x + $w - 72, $y + 54, $code, 7);
        $pdf->setY($y + $h + 12);
    }

    private function infoRows(SimplePdf $pdf, array $rows, bool $spacer = true): void
    {
        $x = 34; $labelW = 148; $valueW = $pdf->contentWidth() - $labelW;
        foreach ($rows as [$label, $value]) {
            $lines = max(count($pdf->wrappedLines((string) $label, $labelW - 8, 8)), count($pdf->wrappedLines((string) $value, $valueW - 8, 8)));
            $h = max(21, $lines * 10 + 7);
            $pdf->ensureSpace($h + 4, $this->pageHeader());
            $y = $pdf->y();
            $pdf->cell($x, $y, $labelW, $h, (string) $label, 8, false);
            $pdf->cell($x + $labelW, $y, $valueW, $h, $this->dash($value), 8, in_array($label, ['Nama Perusahaan', 'Nama Pemohon'], true));
            $pdf->moveY($h);
        }

        // Baris berpilihan menyusul langsung di bawah blok ini, jadi jaraknya
        // ditahan agar tabel identitas tetap menyatu seperti pada formulir.
        if ($spacer) {
            $pdf->moveY(10);
        }
    }

    /**
     * Tanggal bergaya formulir GIS ("18 Maret 2020").
     */
    private function indonesianDate(\DateTimeInterface $date): string
    {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $date->format('j').' '.$months[(int) $date->format('n')].' '.$date->format('Y');
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
        $pdf->ensureSpace(112, $this->pageHeader());
        $x = 34; $w = $pdf->contentWidth(); $status = $review['status'] ?? 'in_progress';

        /*
         * Keputusan dicetak seperti formulir: "Diterima / Ditolak *)" dengan
         * opsi yang tidak berlaku dicoret. Selama belum diputuskan, keduanya
         * dibiarkan utuh persis seperti formulir kosong.
         */
        $options = array_values(config('review.decision_options'));
        $decision = match (true) {
            in_array($status, ['approved', 'accepted'], true) => config('review.decision_options.approved'),
            $status === 'rejected' => config('review.decision_options.rejected'),
            default => null,
        };

        $notes = $review['rejection_reason'] ?? $review['notes'] ?? '-';

        /*
         * action_date pada snapshot sudah berbentuk ISO UTC hasil toArray(),
         * jadi harus dikembalikan ke zona waktu aplikasi sebelum diformat —
         * tanpa ini tanggal formulir bisa mundur satu hari.
         */
        $date = ! empty($review['action_date'])
            ? $this->indonesianDate(Carbon::parse($review['action_date'])->timezone(config('app.timezone')))
            : $this->indonesianDate(now());
        $name = $review['signed_name'] ?? '-';
        $y = $pdf->y();
        $pdf->cell($x, $y, $w - 175, 24, $title, 8, true);
        $pdf->rect($x + $w - 175, $y, 175, 24);
        $cursor = $this->choiceRow($pdf, $x + $w - 169, $y + 16, $options, $decision, 9);
        $pdf->text($cursor, $y + 16, ' *)', 9);
        $pdf->moveY(24); $y = $pdf->y();

        $boxH = 82; $rx = $x + $w - 175; $rw = 175;
        $pdf->cell($x, $y, $w - 175, $boxH, 'Alasan jika ditolak : '.$this->dash($notes), 8, false);
        // Kolom kanan: tanggal, area tanda tangan (gambar), garis, lalu nama.
        $pdf->rect($rx, $y, $rw, $boxH);
        $pdf->text($rx + 6, $y + 16, "Tanggal : {$date}", 8, true);
        $pdf->text($rx + 6, $y + 30, 'Nama dan tanda tangan :', 8, true);
        $this->drawSignature($pdf, $review['signature_path'] ?? null, $rx + 8, $y + 36, $rw - 16, 46);
        $pdf->line($rx + 6, $y + $boxH - 18, $rx + $rw - 6, $y + $boxH - 18, 0.4);
        $pdf->text($rx + 6, $y + $boxH - 6, '('.$name.')', 8, true);
        $pdf->moveY($boxH + 12);
    }

    /**
     * Tata letak mengikuti formulir FrM.9107/GIS: pilihan dicetak berjajar dan
     * yang tidak dipilih dicoret ("*) coret yang tidak perlu").
     */
    private function renderLssm(SimplePdf $pdf, array $s): void
    {
        $meta = $this->formMeta($s);
        $this->renderHeader($pdf, $meta['title'], $meta['code']);

        $this->frmIdentity($pdf, $s);

        [$admin, $technical] = $this->documentsByStage($s);

        $this->frmDocumentTable($pdf, 'I. Administrasi', $admin, 'administration');
        $this->frmNotes($pdf, $s['administration_review']['notes'] ?? null);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian administrasi', $s['administration_review']);

        $pdf->addPage();
        $this->renderHeader($pdf, $meta['title'], $meta['code']);
        $this->frmDocumentTable($pdf, 'II. Teknis', $technical ?: $admin, 'technical');
        $this->frmNotes($pdf, $s['technical_review']['notes'] ?? null);
        $this->frmTechnicalConclusion($pdf, $s);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian teknis', $s['technical_review']);
    }

    /**
     * Formulir FrM.9101/GIS untuk sistem manajemen lingkungan (ISO 14001).
     *
     * Bedanya dengan FrM.9107: blok identitas memakai NACE Code dan hanya satu
     * baris berpilihan (Jenis audit), ditambah tabel kompetensi spesifik auditor
     * 6.1–6.7 pada bagian teknis.
     */
    private function renderLsml(SimplePdf $pdf, array $s): void
    {
        $meta = $this->formMeta($s);
        $this->renderHeader($pdf, $meta['title'], $meta['code']);
        $v = $s['values'];
        $a = $s['application'];

        $this->frmOrderRow($pdf, $a);
        $this->infoRows($pdf, [
            ['Nama Perusahaan', $a['company_name']],
            ['Alamat Perusahaan', $v['company_address'] ?? '-'],
            ['Lingkup Usaha', $v['industry_scope'] ?? $v['business_sector'] ?? '-'],
            ['NACE Code', $v['nace_code'] ?? '-'],
            ['Lingkup Usaha Spesifik (Produk atau jasa yang dihasilkan)', $v['certification_scope'] ?? '-'],
            ['Kriteria Audit', $s['scheme']['standard'] ?? '-'],
        ], false);

        $auditType = config('review.identity_choices.certification_type');
        $chosen = $auditType['map'][$v['certification_type'] ?? ''] ?? null;
        $options = $auditType['options'];

        if ($chosen !== null && ! in_array($chosen, $options, true)) {
            $options[] = $chosen;
        }

        $this->choiceInfoRow($pdf, 'Jenis audit', $options, $chosen);
        $pdf->moveY(10);

        [$admin, $technical] = $this->documentsByStage($s);

        $this->frmDocumentTable($pdf, 'I. Administrasi', $admin, 'administration');
        $this->frmNotes($pdf, $s['administration_review']['notes'] ?? null);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian administrasi', $s['administration_review']);

        $pdf->addPage();
        $this->renderHeader($pdf, $meta['title'], $meta['code']);
        $this->frmDocumentTable($pdf, 'II. Teknis', $technical ?: $admin, 'technical');
        $this->frmNotes($pdf, $s['technical_review']['notes'] ?? null);
        $this->frmTechnicalConclusion($pdf, $s);
        $this->frmCompetenceGrid($pdf, $s);
        $this->decisionBox($pdf, 'Hasil tinjauan permohonan bagian teknis', $s['technical_review']);
    }

    /**
     * Baris tabel administrasi dan teknis persis seperti formulir cetaknya.
     *
     * Diambil dari snapshot agar PDF yang dibuat ulang tetap memuat baris yang
     * sama dengan saat kajian dilakukan.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function documentsByStage(array $s): array
    {
        return [$s['rows']['administration'] ?? [], $s['rows']['technical'] ?? []];
    }

    /**
     * Tabel "Kompetensi spesifik auditor untuk SNI ISO 14001:2015 yang
     * diperlukan" — tujuh kolom aspek dengan tanda centang pada yang dipilih
     * Tim Teknis.
     */
    private function frmCompetenceGrid(SimplePdf $pdf, array $s): void
    {
        $competences = config('review.environmental_competences');
        $selected = $s['technical_review']['auditor_competence_codes'] ?? [];

        $x = 34;
        $w = $pdf->contentWidth();
        $headerH = 46;
        $markH = 26;

        $pdf->ensureSpace($headerH + $markH + 30, $this->pageHeader());
        $y = $pdf->y();
        $pdf->cell($x, $y, $w, 20, 'Kompetensi spesifik auditor untuk '.($s['scheme']['standard'] ?? '-').' yang diperlukan :', 8, true);
        $pdf->moveY(20);

        $y = $pdf->y();
        $columnWidth = $w / count($competences);
        $cursor = $x;

        foreach ($competences as $code => $label) {
            $pdf->cell($cursor, $y, $columnWidth, $headerH, $label.' ('.$code.')', 6.5, false, 'center');
            $cursor += $columnWidth;
        }

        $pdf->moveY($headerH);
        $y = $pdf->y();
        $cursor = $x;

        foreach ($competences as $code => $label) {
            $pdf->rect($cursor, $y, $columnWidth, $markH);

            if (in_array($code, (array) $selected, true)) {
                $this->drawCheckMark($pdf, $cursor + ($columnWidth / 2) - 4, $y + ($markH / 2));
            }

            $cursor += $columnWidth;
        }

        $pdf->moveY($markH + 12);
    }

    /**
     * Tanda centang digambar dari dua garis, bukan karakter, karena font
     * Helvetica bawaan PDF tidak memuat glyph centang.
     */
    private function drawCheckMark(SimplePdf $pdf, float $x, float $y): void
    {
        $pdf->line($x, $y, $x + 3, $y + 4, 1.1);
        $pdf->line($x + 3, $y + 4, $x + 8, $y - 5, 1.1);
    }

    /**
     * Blok identitas FrM.9107, termasuk tiga baris berpilihan yang dicetak
     * dengan coretan (Jenis Sertifikasi, Area Audit, Jenis Audit).
     */
    private function frmIdentity(SimplePdf $pdf, array $s): void
    {
        $v = $s['values'];
        $a = $s['application'];

        $this->frmOrderRow($pdf, $a);

        $rows = [
            ['Nama Perusahaan', $a['company_name']],
            ['Alamat Perusahaan', $v['company_address'] ?? $v['head_office_address'] ?? '-'],
            ['Lingkup Industri', $v['industry_scope'] ?? $v['business_sector'] ?? '-'],
            ['IAF Code', $v['iaf_code'] ?? '-'],
            ['Lingkup Sertifikasi dan batasan : (Produk/Jasa/Proses pada perusahaan)', $v['certification_scope'] ?? '-'],
        ];

        /*
         * Baris identitas tambahan milik formulir tertentu — FrM.9111 (SMK3)
         * menyisipkan Tingkat Resiko dan Bahasa sebelum Standar Audit.
         */
        foreach ($this->formMeta($s)['extra_identity'] ?? [] as $extra) {
            $rows[] = [$extra['label'], $this->identityValue($v, $extra['field'])];
        }

        $rows[] = ['Standar Audit', $s['scheme']['standard'] ?? '-'];

        $this->infoRows($pdf, $rows, false);

        $choices = config('review.identity_choices');
        $siteCount = $s['administration_review']['site_count'] ?? null;

        foreach ($choices as $code => $meta) {
            $chosen = $meta['map'][$v[$code] ?? ''] ?? null;
            // Nilai di luar tiga opsi formulir (mis. Surveillance) ditulis apa adanya.
            $options = $meta['options'];
            if ($chosen !== null && ! in_array($chosen, $options, true)) {
                $options[] = $chosen;
            }

            $suffix = $code === 'site_type'
                ? '  (jumlah site jika multi: '.($siteCount ?: '____').')'
                : '';

            $this->choiceInfoRow($pdf, $meta['label'], $options, $chosen, $suffix);
        }

        $pdf->moveY(10);
    }

    /**
     * Baris pertama formulir: Hari/tanggal, No Order, dan Tanggal Order berada
     * pada satu baris berisi enam sel bergaris, seperti FrM.9107 aslinya.
     */
    private function frmOrderRow(SimplePdf $pdf, array $a): void
    {
        $x = 34;
        $w = $pdf->contentWidth();
        $h = 21;

        $cells = [
            ['Hari/tanggal', 0.15, false],
            [$a['review_date'], 0.16, false],
            ['No Order', 0.11, false],
            [$a['order_number'], 0.26, true],
            ['Tanggal Order', 0.15, false],
            [$a['order_date'], 0.17, false],
        ];

        $pdf->ensureSpace($h + 4, $this->pageHeader());
        $y = $pdf->y();
        $cursor = $x;

        foreach ($cells as [$text, $ratio, $bold]) {
            $cellWidth = $w * $ratio;
            $pdf->cell($cursor, $y, $cellWidth, $h, (string) $text, 8, $bold);
            $cursor += $cellWidth;
        }

        $pdf->moveY($h);
    }

    /**
     * Satu baris label + daftar pilihan bercoret, memakai lebar kolom yang sama
     * dengan infoRows() supaya kedua jenis baris tetap sejajar.
     */
    private function choiceInfoRow(SimplePdf $pdf, string $label, array $options, ?string $chosen, string $suffix = ''): void
    {
        $x = 34;
        $labelW = 148;
        $valueW = $pdf->contentWidth() - $labelW;
        $lines = count($pdf->wrappedLines($label, $labelW - 8, 8));
        $h = max(21, $lines * 10 + 7);

        $pdf->ensureSpace($h + 4, $this->pageHeader());
        $y = $pdf->y();
        $pdf->cell($x, $y, $labelW, $h, $label, 8, false);
        $pdf->rect($x + $labelW, $y, $valueW, $h);

        $baseline = $y + ($h / 2) + 3;
        $cursor = $this->choiceRow($pdf, $x + $labelW + 4, $baseline, $options, $chosen, 8);
        $pdf->text($cursor, $baseline, ' *)'.$suffix, 8);
        $pdf->moveY($h);
    }

    /**
     * Mencetak pilihan berjajar dan mencoret yang tidak dipilih.
     * Mengembalikan posisi x setelah opsi terakhir.
     */
    private function choiceRow(SimplePdf $pdf, float $x, float $baseline, array $options, ?string $chosen, float $size = 8, string $separator = ' / '): float
    {
        $cursor = $x;

        foreach (array_values($options) as $index => $option) {
            if ($index > 0) {
                $pdf->text($cursor, $baseline, $separator, $size);
                $cursor += $pdf->textWidth($separator, $size);
            }

            $pdf->text($cursor, $baseline, $option, $size);
            $width = $pdf->textWidth($option, $size);

            /*
             * Coretan hanya digambar bila sudah ada yang dipilih; selama belum
             * dipilih seluruh opsi dibiarkan utuh seperti formulir kosong.
             */
            if ($chosen !== null && $option !== $chosen) {
                $strikeY = $baseline - ($size * 0.30);
                $pdf->line($cursor, $strikeY, $cursor + $width, $strikeY, 0.6);
            }

            $cursor += $width;
        }

        return $cursor;
    }

    /**
     * Tabel dokumen FrM.9107: kolom Hasil Kajian dan Keterangan dicetak sebagai
     * pilihan bercoret, bukan teks status.
     */
    private function frmDocumentTable(SimplePdf $pdf, string $title, array $items, string $stage = 'administration'): void
    {
        $resultOptions = array_values(config('review.result_options'));
        $remarkLabels = config('review.remark_options');

        $pdf->ensureSpace(70, $this->pageHeader());
        $pdf->text(34, $pdf->y() + 10, $title, 11, true);
        $pdf->moveY(18);

        $x = 34;
        $widths = [28, 232, 120, $pdf->contentWidth() - 380];
        $y = $pdf->y();
        $cx = $x;
        foreach (['No.', 'Dokumen', 'Hasil Kajian*)', 'Keterangan*)'] as $i => $header) {
            $pdf->fillRect($cx, $y, $widths[$i], 25, .92);
            $pdf->cell($cx, $y, $widths[$i], 25, $header, 8, true, 'center');
            $cx += $widths[$i];
        }
        $pdf->moveY(25);

        foreach (array_values($items) as $index => $item) {
            $label = $item['name'] ?? $item['item_label'] ?? '-';
            // Hasil tahap ini; dokumen yang dikaji dua kali punya nilai berbeda.
            $result = $item['stages'][$stage] ?? $item;
            $chosenResult = config('review.result_options')[$result['review_status'] ?? ''] ?? null;

            $remarkOption = $result['remark_option'] ?? '';
            $chosenRemark = $remarkLabels[$remarkOption] ?? null;

            /*
             * Baris yang memakai masa berlaku (mis. NIB) hanya mencetak tanggal
             * itu saja — persis formulir aslinya, di mana Sesuai/Belum Sesuai
             * tidak relevan untuk dokumen bertanggal berlaku.
             */
            /*
             * Baris penilaian (bukan dokumen) memakai keterangan teks bebas
             * seperti pada formulir, jadi tidak ada pilihan yang dicoret.
             */
            $freeRemark = ! empty($item['free_remark']);
            $usesDate = ! $freeRemark && $remarkOption === 'tgl_berlaku';

            if ($freeRemark) {
                $remarkLines = [[trim((string) ($result['notes'] ?? '')) ?: '____']];
            } elseif ($usesDate) {
                $remarkLines = [[$remarkLabels['tgl_berlaku'].' : '.($result['remark_date'] ?: '____')]];
            } else {
                $remarkLines = [[$remarkLabels['sesuai'], $remarkLabels['belum_sesuai']]];
            }

            $lines = count($pdf->wrappedLines($label, $widths[1] - 8, 7.5));
            $h = max(26, $lines * 10 + 12);

            $pdf->ensureSpace($h + 32, $this->pageHeader($title.' (lanjutan)'));

            $y = $pdf->y();
            $pdf->cell($x, $y, $widths[0], $h, (string) ($index + 1), 7.5, false, 'center');
            $pdf->cell($x + $widths[0], $y, $widths[1], $h, $label, 7.5, false);

            $rx = $x + $widths[0] + $widths[1];
            $pdf->rect($rx, $y, $widths[2], $h);
            $this->choiceRow($pdf, $rx + 4, $y + ($h / 2) + 3, $resultOptions, $chosenResult, 7.5);

            $kx = $rx + $widths[2];
            $pdf->rect($kx, $y, $widths[3], $h);
            $this->choiceRow($pdf, $kx + 4, $y + ($h / 2) + 3, $remarkLines[0], ($usesDate || $freeRemark) ? null : $chosenRemark, 7.5);

            // Catatan bebas ditulis di kolom dokumen bila peninjau mengisinya.
            if (! $freeRemark && filled($result['notes'] ?? null)) {
                $pdf->text($x + $widths[0] + 4, $y + $h - 4, mb_substr((string) $result['notes'], 0, 70), 6.5);
            }

            $pdf->moveY($h);
        }

        $pdf->moveY(6);
        $pdf->paragraph('*) coret yang tidak perlu', 7.5, 10);
        $pdf->paragraph('Note : Seluruh dokumen wajib diberikan oleh pemohon sebelum proses sertifikasi berlanjut ke tahap berikutnya.', 7.5, 10);
    }

    private function frmNotes(SimplePdf $pdf, ?string $notes): void
    {
        $x = 34;
        $w = $pdf->contentWidth();
        $text = 'Catatan Belum Memenuhi : '.$this->dash($notes);
        $lines = count($pdf->wrappedLines($text, $w - 8, 8));
        $h = max(26, $lines * 11 + 8);

        $pdf->ensureSpace($h + 6, $this->pageHeader());
        $pdf->cell($x, $pdf->y(), $w, $h, $text, 8, false);
        $pdf->moveY($h + 8);
    }

    /**
     * Tiga baris kesimpulan teknis berpilihan + isian auditor/panelis.
     */
    private function frmTechnicalConclusion(SimplePdf $pdf, array $s): void
    {
        $review = $s['technical_review'] ?? [];
        $v = $s['values'];
        $config = config('review.technical_conclusion');
        $formMeta = $this->formMeta($s);

        foreach (['scope_conformity', 'audit_capability_choice'] as $key) {
            $meta = $config[$key];
            $chosen = $meta['options'][$review[$key] ?? ''] ?? null;
            // Nama lembaga menyesuaikan skema: LSSM, LSSMLTI, dst.
            $label = str_replace(':body', $formMeta['body'], $meta['label']);
            $this->choiceInfoRow($pdf, $label, array_values($meta['options']), $chosen);
        }

        /*
         * Mandays tidak punya pilihan pada formulir: perhitungannya dilampirkan
         * lewat formulir terpisah, jadi selnya berisi teks tetap itu saja.
         */
        $this->infoRows($pdf, [
            ['Mandays audit (stage 1 + Stage 2)', $formMeta['mandays_text']],
            ['Kompetensi auditor yang diperlukan', $v['required_auditor_competence'] ?? '-'],
            ['Tim Auditor yang ditugaskan (LA, A, TA)', $this->numberedList($s['assigned_auditors'] ?? [])],
            ['Panelis yang ditugaskan', $this->numberedList($s['assigned_panelists'] ?? [])],
        ]);
    }

    /**
     * Formulir Fr.7201/GIS-4 — Tinjauan Permohonan Sertifikasi Produk (LSPro).
     *
     * Berbeda dari formulir LSSM, tabelnya punya dua kolom hasil kajian:
     * Pengoleksi diisi Admin Permohonan, sedangkan Peninjau adalah verifikasi
     * Tim Teknis atas pekerjaan Admin — Tim Teknis tidak menilai ulang baris
     * per baris, melainkan menerima seluruhnya atau mengembalikannya untuk
     * diperbaiki. Karena itu kolom Peninjau baru terisi setelah tinjauan
     * teknis disetujui.
     *
     * Bagian bawah memuat tiga kotak tanda tangan: Pengoleksi (Admin),
     * Peninjau (Tim Teknis), dan Pihak Klien yang diambil dari unggahan
     * tanda tangan pemohon pada formulir permohonan.
     */
    private function renderSni(SimplePdf $pdf, array $s): void
    {
        $this->renderHeader($pdf, 'TINJAUAN PERMOHONAN SERTIFIKASI PRODUK (LSPro)', 'Fr.7201/GIS-4');
        $v = $s['values'];
        $a = $s['application'];

        $this->frmOrderRow($pdf, $a);
        $this->infoRows($pdf, [
            ['Nama & Alamat Pemohon', trim($a['company_name'].' - '.($v['company_address'] ?? '')) ?: '-'],
            ['Produk', $v['product_category'] ?? '-'],
            ['Tipe/Kategori', $v['product_name'] ?? '-'],
            ['Nomor Standar SNI', $v['sni_number'] ?? '-'],
            ['Merek', $v['brand'] ?? '-'],
            ['Nama dan Alamat Shipper (Import)', trim(($v['shipper_name'] ?? '').' '.($v['shipper_address'] ?? '')) ?: '-'],
            ['Nama dan Alamat Pengambilan Contoh', trim(($v['producer_name'] ?? '').' '.($v['sampling_address'] ?? '')) ?: '-'],
        ]);

        $this->sniDocumentTable($pdf, $s);
        $this->frmNotes($pdf, $s['administration_review']['notes'] ?? null);
        $this->sniSignatureBoxes($pdf, $s);
    }

    /**
     * Tabel checklist Fr.7201 dengan dua kolom hasil kajian.
     *
     * Kolom Peninjau mengikuti hasil Admin dan hanya dicetak bila tinjauan
     * teknis sudah disetujui; selama belum, selnya dibiarkan kosong supaya
     * terlihat bahwa verifikasinya memang belum dilakukan.
     */
    private function sniDocumentTable(SimplePdf $pdf, array $s): void
    {
        $options = array_values(config('review.result_options'));
        $verified = in_array($s['technical_review']['status'] ?? '', ['approved', 'accepted'], true);

        $pdf->ensureSpace(70, $this->pageHeader());
        $pdf->text(34, $pdf->y() + 10, 'Checklist Dokumen Permohonan', 11, true);
        $pdf->moveY(18);

        $x = 34;
        $widths = [26, 214, 104, 104, $pdf->contentWidth() - 448];
        $headers = ['No', 'Dokumen', 'Hasil Kajian Pengoleksi*)', 'Hasil Kajian Peninjau*)', 'Keterangan'];

        $y = $pdf->y();
        $cx = $x;
        foreach ($headers as $i => $header) {
            $pdf->fillRect($cx, $y, $widths[$i], 30, .92);
            $pdf->cell($cx, $y, $widths[$i], 30, $header, 7.5, true, 'center');
            $cx += $widths[$i];
        }
        $pdf->moveY(30);

        foreach (array_values($s['documents']) as $index => $item) {
            $label = $item['name'] ?? '-';
            $status = $item['stages']['administration']['review_status'] ?? 'pending';
            $chosen = config('review.result_options')[$status] ?? null;
            $note = $item['stages']['administration']['notes'] ?? $item['notes'] ?? '';

            $lines = max(
                count($pdf->wrappedLines($label, $widths[1] - 8, 7)),
                count($pdf->wrappedLines($note ?: '-', $widths[4] - 8, 7))
            );
            $h = max(26, $lines * 9 + 12);

            $pdf->ensureSpace($h + 32, $this->pageHeader('Checklist Dokumen Permohonan (lanjutan)'));
            $y = $pdf->y();

            $pdf->cell($x, $y, $widths[0], $h, (string) ($index + 1), 7, false, 'center');
            $pdf->cell($x + $widths[0], $y, $widths[1], $h, $label, 7, false);

            $cx = $x + $widths[0] + $widths[1];
            $pdf->rect($cx, $y, $widths[2], $h);
            $this->choiceRow($pdf, $cx + 4, $y + ($h / 2) + 3, $options, $chosen, 7);

            $cx += $widths[2];
            $pdf->rect($cx, $y, $widths[3], $h);
            // Verifikasi teknis bersifat menyeluruh, jadi mengikuti hasil Admin.
            $this->choiceRow($pdf, $cx + 4, $y + ($h / 2) + 3, $options, $verified ? $chosen : null, 7);

            $pdf->cell($cx + $widths[3], $y, $widths[4], $h, $note ?: '-', 7, false);
            $pdf->moveY($h);
        }

        $pdf->moveY(6);
        $pdf->paragraph('*) Coret salah satu', 7.5, 10);
        $pdf->paragraph('**) Apabila ada', 7.5, 10);
        $pdf->moveY(4);
    }

    /**
     * Tiga kotak tanda tangan Fr.7201.
     *
     * Dua kotak pertama memakai tanda tangan akun peninjau; kotak Pihak Klien
     * memakai gambar yang diunggah pemohon pada bagian Pernyataan.
     */
    private function sniSignatureBoxes(SimplePdf $pdf, array $s): void
    {
        $w = $pdf->contentWidth();
        $colW = $w / 3;
        $h = 104;

        $pdf->ensureSpace($h + 30, $this->pageHeader());
        $y = $pdf->y();

        $admin = $s['administration_review'] ?? null;
        $technical = $s['technical_review'] ?? null;
        $client = collect($s['values']['client_signature'] ?? [])->first() ?? [];

        $columns = [
            ["Pihak LSPro
(Pengoleksi Dokumen Permohonan)", 'Staf Administrasi', $admin['signed_name'] ?? null, $admin['signature_path'] ?? null],
            ["Pihak LSPro
(Peninjau Dokumen Permohonan)", 'Manajer Administrasi', $technical['signed_name'] ?? null, $technical['signature_path'] ?? null],
            ['Pihak Klien', '', $client['nama'] ?? null, $client['tanda_tangan'] ?? null],
        ];

        $cursor = 34;
        foreach ($columns as [$title, $role, $name, $signature]) {
            $pdf->rect($cursor, $y, $colW, $h);

            foreach ($pdf->wrappedLines($title, $colW - 12, 7.5) as $i => $line) {
                $pdf->text($cursor + 6, $y + 14 + ($i * 10), $line, 7.5);
            }

            $this->drawSignature($pdf, $signature, $cursor + 10, $y + 38, $colW - 20, 38);

            if ($role !== '') {
                $pdf->text($cursor + 6, $y + $h - 16, $role, 7.5);
            }
            $pdf->text($cursor + 6, $y + $h - 5, '('.($name ?: '.....................................').')', 7.5);

            $cursor += $colW;
        }

        $pdf->moveY($h + 10);
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
