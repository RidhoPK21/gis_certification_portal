<?php

namespace App\Services;

use App\Models\ApplicationReview;
use App\Models\ApplicationValue;
use App\Models\CertificationApplication;
use App\Models\ReviewFormItem;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Nilai hasil kajian yang diterima, dipakai bersama oleh form admin
     * dan form Tim Teknis agar aturan validasinya tidak divergen.
     */
    public const STATUSES = ['pending', 'sufficient', 'insufficient', 'meets', 'not_meets'];

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

            $review = ApplicationReview::updateOrCreate(
                ['application_id' => $application->id, 'review_type' => $type, 'round' => $round, 'status' => 'in_progress'],
                ['notes' => $data['notes'] ?? null, 'action_date' => $data['action_date'], 'signed_name' => $data['signed_name'], 'reviewed_by' => $userId]
            );

            foreach ($data['items'] ?? [] as $index => $item) {
                if ($item['type'] === 'document') {
                    abort_if(
                        $allowedDocumentCodes !== null && ! in_array($item['code'], $allowedDocumentCodes, true),
                        422,
                        'Dokumen "'.$item['label'].'" bukan bagian dari tahap kajian ini.'
                    );
                }

                ReviewFormItem::updateOrCreate(
                    ['application_review_id' => $review->id, 'item_type' => $item['type'], 'item_code' => $item['code']],
                    ['item_label' => $item['label'], 'presence_status' => $item['presence'] ?? null, 'review_status' => $item['status'], 'notes' => $item['notes'] ?? null, 'sort_order' => $index]
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
     * @return array<int, string>
     */
    private function documentCodesForGroup(
        CertificationApplication $application,
        string $group
    ): array {
        return $this->forms->schemeForApplication($application)->requiredDocuments
            ->filter(fn ($doc) => ($doc->review_group ?? 'administration') === $group)
            ->pluck('code')
            ->all();
    }
}
