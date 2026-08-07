<?php

namespace App\Services;

use App\Models\CertificationApplication;

/**
 * Menyusun struktur formulir Tinjauan Permohonan ISPO (FrO.7204/GIS-3).
 *
 * Formulir ini berbeda dari tinjauan skema lain: hasil kajiannya berupa kolom
 * bercentang (Cukup / Belum Cukup) dengan Keterangan yang diketik, dan
 * bagian yang ditinjau menyesuaikan ruang lingkup yang dipilih pemohon pada
 * Form Aplikasi — kelompok checklist untuk ruang lingkup yang tidak dimohon
 * tidak ditampilkan sama sekali.
 *
 * Pembagian tugas mengikuti formulir:
 *   Admin Permohonan  -> bagian 1-3, menandatangani sebagai Peninjau
 *   Tim Teknis        -> bagian 4-8, menandatangani sebagai Approval
 */
class IspoReviewService
{
    /** Bagian yang menjadi tanggung jawab masing-masing tahap. */
    public const ADMIN_SECTIONS = ['identity', 'scope', 'documents'];

    public const TECHNICAL_SECTIONS = ['data_conformity', 'section_completeness', 'capability', 'mandays', 'assignment', 'decision'];

    public function __construct(private readonly DynamicFormService $forms)
    {
    }

    public function isIspo(CertificationApplication $application): bool
    {
        return $application->scheme->review_template === 'ispo';
    }

    /**
     * Ruang lingkup yang dipilih pemohon pada Form Aplikasi bagian A.
     *
     * @return array<int, string>
     */
    public function applicantScopes(CertificationApplication $application): array
    {
        $values = $this->forms->values($application);

        return array_values(array_filter((array) ($values['applicant_type'] ?? [])));
    }

    /**
     * Kelompok checklist bagian 3 yang berlaku bagi permohonan ini.
     *
     * Bila pemohon belum memilih ruang lingkup sama sekali, seluruh kelompok
     * dikembalikan agar peninjau tetap bisa bekerja alih-alih melihat formulir
     * kosong tanpa penjelasan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function documentGroups(CertificationApplication $application): array
    {
        $scopes = $this->applicantScopes($application);
        $groups = config('review.ispo.document_groups');

        if (! $scopes) {
            return $groups;
        }

        return array_values(array_filter(
            $groups,
            fn (array $group) => (bool) array_intersect($group['scope'], $scopes)
        ));
    }

    /**
     * Seluruh baris yang harus dinilai pada satu tahap, sudah rata sehingga
     * form dan PDF membacanya dari sumber yang sama.
     *
     * @return array<int, array{type: string, code: string, label: string, group: ?string, options: array<string, string>}>
     */
    public function rows(CertificationApplication $application, string $stage): array
    {
        $rows = [];

        if ($stage === 'administration') {
            foreach ($this->documentGroups($application) as $group) {
                foreach ($group['rows'] as $row) {
                    $rows[] = [
                        'type' => 'ispo_document',
                        // Kode dibuat unik per kelompok: baris seperti "Surat
                        // Permohonan Sertifikasi" muncul di beberapa kelompok
                        // dan hasilnya tidak boleh saling menimpa.
                        'code' => $group['code'].'.'.$row['code'],
                        'label' => $row['label'],
                        'group' => $group['title'],
                        'options' => config('review.ispo.result_options'),
                    ];
                }
            }

            return $rows;
        }

        foreach (config('review.ispo.data_conformity') as $row) {
            $rows[] = [
                'type' => 'ispo_data_conformity',
                'code' => $row['code'],
                'label' => $row['label'],
                'group' => '4. Kesesuaian Data Formulir Aplikasi',
                'options' => config('review.ispo.result_options'),
            ];
        }

        foreach (config('review.ispo.section_completeness') as $row) {
            $rows[] = [
                'type' => 'ispo_section_completeness',
                'code' => $row['code'],
                'label' => $row['label'],
                'group' => '4.1 Kelengkapan Bagian Khusus Sesuai Ruang Lingkup',
                'options' => config('review.ispo.completeness_options'),
            ];
        }

        foreach (config('review.ispo.capability_review') as $row) {
            $rows[] = [
                'type' => 'ispo_capability',
                'code' => $row['code'],
                'label' => $row['label'],
                'group' => '5. Kaji Ulang Kemampuan LS ISPO',
                'options' => config('review.ispo.capability_options'),
            ];
        }

        return $rows;
    }

    /**
     * Baris dikelompokkan menurut judul bagiannya, untuk dirender sebagai tabel
     * terpisah pada form maupun PDF.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupedRows(CertificationApplication $application, string $stage): array
    {
        $grouped = [];

        foreach ($this->rows($application, $stage) as $row) {
            $grouped[$row['group']][] = $row;
        }

        return $grouped;
    }

    /**
     * Hasil kajian yang sudah tersimpan, dipetakan per "tipe.kode" agar form
     * dapat memilih ulang nilainya.
     *
     * @return array<string, array{status: string, notes: ?string}>
     */
    public function savedItems(CertificationApplication $application, string $stage): array
    {
        $review = $application->reviews()
            ->where('review_type', $stage)
            ->latest('id')
            ->first();

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
}
