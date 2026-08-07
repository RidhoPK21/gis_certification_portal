<?php

namespace App\Services;

use App\Models\ApplicationReview;
use App\Models\ApplicationValue;
use App\Models\CertificationApplication;
use App\Models\ReviewFormItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Nilai hasil kajian yang diterima, dipakai bersama oleh form admin
     * dan form Tim Teknis agar aturan validasinya tidak divergen.
     *
     * 'meets'/'not_meets' dipertahankan demi data lama; formulir FrM.9107
     * hanya menawarkan Cukup (sufficient) dan Belum Cukup (insufficient).
     */
    public const STATUSES = ['pending', 'sufficient', 'insufficient', 'meets', 'not_meets'];

    /**
     * Pilihan kolom "Keterangan*)" pada FrM.9107.
     */
    public const REMARK_OPTIONS = ['sesuai', 'belum_sesuai', 'tgl_berlaku'];

    /**
     * Apakah sebuah dokumen dikaji pada tahap tertentu.
     *
     * review_group 'both' dipakai formulir seperti FrM.9101 yang memuat dokumen
     * yang sama pada tabel administrasi maupun teknis: Admin memeriksa
     * kelengkapannya, Tim Teknis memeriksa substansinya, dan hasilnya berdiri
     * sendiri karena tersimpan pada review_form_items masing-masing tahap.
     */
    public static function documentInGroup(mixed $document, string $group): bool
    {
        $documentGroup = (is_array($document) ? ($document['review_group'] ?? null) : $document->review_group)
            ?? 'administration';

        return $documentGroup === $group || $documentGroup === 'both';
    }

    public function __construct(
        private readonly DynamicFormService $forms
    ) {
    }

    /**
     * Menyimpan satu bagian tinjauan permohonan (administrasi/teknis)
     * beserta item-itemnya. Dipakai bersama oleh alur admin (administrasi)
     * dan Tim Teknis (teknis) agar logika persistensinya tidak terduplikasi.
     *
     * $documentGroup membatasi dokumen mana yang boleh disentuh tahap ini
     * (lihat scheme_required_documents.review_group). Tanpa pagar tersebut
     * kajian admin dan kajian teknis bisa saling menimpa review_status pada
     * dokumen yang sama.
     *
     * @param  array{notes?: ?string, action_date: string, signed_name: string, items?: array<int, array<string, mixed>>}  $data
     */
    public function save(
        CertificationApplication $application,
        string $type,
        array $data,
        int $userId,
        ?string $documentGroup = null
    ): ApplicationReview {
        $allowedDocumentCodes = $documentGroup === null
            ? null
            : $this->documentCodesForGroup($application, $documentGroup);

        return DB::transaction(function () use ($application, $type, $data, $userId, $allowedDocumentCodes): ApplicationReview {
            $round = ((int) $application->reviews()->where('review_type', $type)->max('round')) ?: 1;

            /*
             * Nama penanda tangan selalu diambil dari akun yang mengisi kajian,
             * bukan dari isian bebas: yang tercetak di formulir harus benar-benar
             * personel GIS yang bertanggung jawab atas tinjauan ini.
             */
            $attributes = [
                'notes' => $data['notes'] ?? null,
                'action_date' => $data['action_date'],
                'signed_name' => User::find($userId)?->name ?? ($data['signed_name'] ?? null),
                'reviewed_by' => $userId,
            ];

            /*
             * Pilihan bercoret FrM.9107 hanya ditimpa bila memang dikirim form,
             * sehingga menyimpan bagian administrasi tidak menghapus kesimpulan
             * teknis yang sudah diisi Tim Teknis (dan sebaliknya).
             */
            foreach (['scope_conformity', 'audit_capability_choice', 'site_count'] as $choice) {
                if (array_key_exists($choice, $data)) {
                    $attributes[$choice] = $data[$choice] !== '' ? $data[$choice] : null;
                }
            }

            if (array_key_exists('panelist_ids', $data)) {
                $panelists = array_values(array_filter(array_map('intval', (array) $data['panelist_ids'])));
                $attributes['panelist_ids'] = $panelists ?: null;
            }

            /*
             * Isian khusus FrO.7204 (mandays, tanggal kelengkapan, keputusan).
             * Digabung dengan yang sudah tersimpan, bukan ditimpa, supaya
             * menyimpan satu bagian tidak menghapus bagian lain pada formulir
             * yang sama.
             */
            if (array_key_exists('ispo', $data)) {
                $existing = ApplicationReview::where('application_id', $application->id)
                    ->where('review_type', $type)
                    ->where('round', $round)
                    ->value('ispo_data') ?? [];

                $attributes['ispo_data'] = array_replace_recursive($existing, (array) $data['ispo']);
            }

            if (array_key_exists('auditor_competence_codes', $data)) {
                $codes = array_values(array_intersect(
                    array_keys(config('review.environmental_competences')),
                    (array) $data['auditor_competence_codes']
                ));
                $attributes['auditor_competence_codes'] = $codes ?: null;
            }

            $review = ApplicationReview::updateOrCreate(
                ['application_id' => $application->id, 'review_type' => $type, 'round' => $round, 'status' => 'in_progress'],
                $attributes
            );

            foreach ($data['items'] ?? [] as $index => $item) {
                if ($item['type'] === 'document') {
                    abort_if(
                        $allowedDocumentCodes !== null && ! in_array($item['code'], $allowedDocumentCodes, true),
                        422,
                        'Dokumen "'.$item['label'].'" bukan bagian dari tahap kajian ini.'
                    );
                }

                $remarkOption = $item['remark_option'] ?? null;

                ReviewFormItem::updateOrCreate(
                    ['application_review_id' => $review->id, 'item_type' => $item['type'], 'item_code' => $item['code']],
                    [
                        'item_label' => $item['label'],
                        'presence_status' => $item['presence'] ?? null,
                        'review_status' => $item['status'],
                        'remark_option' => $remarkOption,
                        // Tanggal hanya bermakna untuk opsi "Tgl Berlaku".
                        'remark_date' => $remarkOption === 'tgl_berlaku' ? ($item['remark_date'] ?? null) : null,
                        'notes' => $item['notes'] ?? null,
                        'sort_order' => $index,
                    ]
                );

                if ($item['type'] === 'document') {
                    $application->documents()->where('document_code', $item['code'])
                        ->update(['review_status' => $item['status'], 'review_note' => $item['notes'] ?? null, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                }
            }

            return $review;
        });
    }

    /**
     * Menyimpan isian aspek teknis (mandays, tim auditor, panelis, dst.)
     * sebagai application_values, supaya ikut tercetak pada PDF tinjauan
     * permohonan yang membacanya lewat snapshot values.
     *
     * @param  array<string, mixed>  $aspects
     */
    public function storeTechnicalAspects(
        CertificationApplication $application,
        array $aspects,
        int $userId
    ): void {
        $fields = config('review.technical_fields');

        DB::transaction(function () use ($application, $aspects, $userId, $fields): void {
            foreach ($aspects as $code => $value) {
                if (! isset($fields[$code])) {
                    continue;
                }

                /*
                 * scheme_field_id sengaja null: aspek teknis bukan field
                 * permohonan milik skema, melainkan hasil kajian Tim Teknis.
                 */
                ApplicationValue::updateOrCreate(
                    ['application_id' => $application->id, 'field_code' => $code],
                    [
                        'scheme_field_id' => null,
                        'value_text' => filled($value) ? (string) $value : null,
                        'value_json' => null,
                        'field_label_snapshot' => $fields[$code]['label'],
                        'field_type_snapshot' => $fields[$code]['input'],
                        'updated_by' => $userId,
                    ]
                );
            }
        });

        $application->unsetRelation('values');
    }

    /**
     * Baris tabel tinjauan untuk satu tahap, mengikuti formulir cetaknya.
     *
     * Tiap baris membawa kode, label persis seperti pada formulir, dan dokumen
     * checklist yang bersesuaian (bila ada) supaya berkasnya bisa dibuka.
     *
     * Sebagian formulir (mis. FrM.9101/GIS-7 untuk LSSMKI) memuat baris
     * penilaian yang bukan dokumen unggahan klien — misalnya "Level MS" atau
     * "Infrastruktur IT dan kompleksitas". Baris seperti itu ditandai
     * 'document' => false pada konfigurasi dan keterangannya berupa teks bebas.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function formRows(CertificationApplication $application, string $stage): Collection
    {
        $scheme = $this->forms->schemeForApplication($application);
        $documents = $scheme->requiredDocuments->keyBy('code');
        $rows = config('review.form_rows.'.$scheme->code.'.'.$stage);

        // Template tanpa daftar baris eksplisit tetap memakai pengelompokan lama.
        if (! $rows) {
            return $scheme->requiredDocuments
                ->filter(fn ($doc) => self::documentInGroup($doc, $stage))
                ->map(fn ($doc) => (object) [
                    'code' => $doc->code,
                    'name' => $doc->name,
                    'document' => $doc,
                    'expects_document' => true,
                    'free_remark' => false,
                ])
                ->values();
        }

        return collect($rows)->map(function (array $row) use ($documents) {
            $expectsDocument = $row['document'] ?? true;

            return (object) [
                'code' => $row['code'],
                'name' => $row['label'],
                'document' => $expectsDocument ? $documents->get($row['code']) : null,
                'expects_document' => $expectsDocument,
                // Keterangan baris penilaian diisi bebas, bukan Sesuai/Belum Sesuai.
                'free_remark' => ($row['remark'] ?? null) === 'text',
            ];
        })->values();
    }

    /**
     * @return array<int, string>
     */
    private function documentCodesForGroup(
        CertificationApplication $application,
        string $group
    ): array {
        return $this->formRows($application, $group)->pluck('code')->all();
    }
}
