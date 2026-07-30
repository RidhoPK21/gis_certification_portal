<?php

namespace App\Services;

use App\Models\ApplicationReview;
use App\Models\CertificationApplication;
use App\Models\ReviewFormItem;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Menyimpan satu bagian tinjauan permohonan (administrasi/teknis)
     * beserta item-itemnya. Dipakai bersama oleh alur admin (administrasi)
     * dan Tim Teknis (teknis) agar logika persistensinya tidak terduplikasi.
     *
     * @param  array{notes?: ?string, action_date: string, signed_name: string, items?: array<int, array<string, mixed>>}  $data
     */
    public function save(
        CertificationApplication $application,
        string $type,
        array $data,
        int $userId
    ): ApplicationReview {
        return DB::transaction(function () use ($application, $type, $data, $userId): ApplicationReview {
            $round = ((int) $application->reviews()->where('review_type', $type)->max('round')) ?: 1;

            $review = ApplicationReview::updateOrCreate(
                ['application_id' => $application->id, 'review_type' => $type, 'round' => $round, 'status' => 'in_progress'],
                ['notes' => $data['notes'] ?? null, 'action_date' => $data['action_date'], 'signed_name' => $data['signed_name'], 'reviewed_by' => $userId]
            );

            foreach ($data['items'] ?? [] as $index => $item) {
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
}
