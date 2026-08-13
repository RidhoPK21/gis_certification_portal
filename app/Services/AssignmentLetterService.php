<?php

namespace App\Services;

use App\Models\AssignmentLetter;
use App\Models\CertificationApplication;
use Illuminate\Support\Carbon;

/**
 * Penyiapan isi Surat Tugas: keluarga skema, nomor surat, prefill isian dari
 * form klien, dan baris tabel auditor. Tidak menggambar PDF apa pun.
 */
class AssignmentLetterService
{
    use Concerns\FormatsRomanMonth;

    public function family(CertificationApplication $application): string
    {
        $template = $application->scheme?->review_template;

        return config('assignment_letter.families.'.$template)
            ?? config('assignment_letter.default_family');
    }

    /**
     * @return array<string, mixed> konfigurasi template untuk keluarga skema ini
     */
    public function templateConfig(CertificationApplication $application): array
    {
        return config('assignment_letter.templates.'.$this->family($application));
    }

    /**
     * Nilai awal isian surat, diambil dari jawaban klien bila ada.
     *
     * Kunci larik mengikuti primary_fields + detail_fields pada config sehingga
     * form dan penggambar PDF membaca kunci yang sama.
     *
     * @return array<string, string>
     */
    public function defaults(CertificationApplication $application, string $stageCode): array
    {
        $family = $this->family($application);

        $common = [
            'order_number' => (string) ($application->order_number ?? ''),
            'assignment_date' => '',
        ];

        $values = match ($family) {
            'lspro' => [
                'company_name' => $this->pick($application, ['company_name']) ?: (string) $application->company_name,
                'company_address' => $this->pick($application, ['company_address']),
                'phone_fax' => $this->phoneFax($application),
                'commodity' => $this->pick($application, ['product_category']),
                'type_brands' => $this->joinFilled([
                    $this->pick($application, ['product_name']),
                    $this->pick($application, ['brand']),
                ], ' / '),
                'sni_number' => $this->pick($application, ['sni_number']),
                'location' => $this->pick($application, ['production_location', 'producer_address']),
                // Tidak ada padanannya di form klien — diisi manual Tim Teknis.
                'laboratory' => '',
                'laboratory_address' => '',
            ],
            'ispo' => [
                'company_name' => $this->pick($application, ['official_name']) ?: (string) $application->company_name,
                'company_address' => $this->pick($application, ['office_address']),
                'phone_fax' => $this->phoneFax($application),
                // Ruang lingkup ISPO berbentuk kelompok centang; prefill sebisanya.
                'scope' => $this->pick($application, ['upstream_scope', 'downstream_kbli', 'main_business']),
                'location' => $this->pick($application, ['office_address']),
            ],
            default => [
                'company_name' => $this->pick($application, ['company_name']) ?: (string) $application->company_name,
                'company_address' => $this->pick($application, ['company_address']),
                'phone_fax' => $this->phoneFax($application),
                'industry_scope' => $this->pick($application, ['industry_scope']),
                'specific_scope' => $this->pick($application, ['certification_scope']),
                'location' => $this->pick($application, ['sites_information', 'company_address']),
                'standard' => (string) ($application->scheme?->standard ?? ''),
            ],
        };

        return array_merge($common, $values);
    }

    /**
     * Baris tabel auditor untuk satu tahap, diurutkan LA → A → TA lalu nama.
     *
     * @return array<int, array{auditor_id: int, name: string, role_code: string, position_label: string, sort: int}>
     */
    public function auditorRows(CertificationApplication $application, string $stageCode): array
    {
        $labels = config('assignment_letter.role_labels');
        $order = array_flip(config('assignment_letter.role_order'));

        $assignments = $application->relationLoaded('auditAssignments')
            ? $application->auditAssignments
            : $application->auditAssignments()->with('auditor')->get();

        return $assignments
            ->where('status', 'assigned')
            ->whereIn('stage_code', ['all', $stageCode])
            // Satu orang bisa punya baris 'all' sekaligus baris tahap ini.
            ->groupBy('auditor_id')
            ->map(fn ($rows) => $rows->sortBy(fn ($row) => $order[$row->assignment_role] ?? 99)->first())
            ->sortBy([
                fn ($a, $b) => ($order[$a->assignment_role] ?? 99) <=> ($order[$b->assignment_role] ?? 99),
                fn ($a, $b) => strcmp((string) $a->auditor?->name, (string) $b->auditor?->name),
            ])
            ->values()
            ->map(fn ($assignment, $index) => [
                'auditor_id' => (int) $assignment->auditor_id,
                'name' => (string) ($assignment->auditor?->name ?? '-'),
                'role_code' => (string) $assignment->assignment_role,
                'position_label' => $labels[$assignment->assignment_role] ?? $assignment->assignment_role,
                'sort' => $index + 1,
            ])
            ->all();
    }

    /**
     * Saran nomor surat berikutnya untuk satu keluarga pada tahun tertentu.
     *
     * @return array{number: string, sequence: int}
     */
    public function suggestNumber(string $family, ?\DateTimeInterface $date = null): array
    {
        $date = $date ? Carbon::instance(Carbon::parse($date)) : now();
        $pattern = config('assignment_letter.templates.'.$family.'.number_pattern');
        $padding = (int) config('assignment_letter.number_padding', 3);

        $last = (int) AssignmentLetter::query()
            ->where('number_family', $family)
            ->whereYear('letter_date', (int) $date->format('Y'))
            ->max('sequence_number');

        $sequence = $last + 1;

        $number = strtr($pattern, [
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
            '{ROMAN_MONTH}' => $this->romanMonth((int) $date->format('n')),
            '{MM}' => $date->format('m'),
            '{YYYY}' => $date->format('Y'),
        ]);

        return ['number' => $number, 'sequence' => $sequence];
    }

    /**
     * Kalimat "untuk melakukan kegiatan audit ..." sesuai tahap.
     */
    public function purposeLine(string $family, string $stageCode): string
    {
        $config = config('assignment_letter.templates.'.$family);
        $purpose = $config['purpose'] ?? '';

        if (! str_contains($purpose, ':stage')) {
            return $purpose;
        }

        $stage = $config['purpose_by_stage'][$stageCode] ?? '';

        // Rapatkan spasi ganda bila tahapnya tidak dikenali.
        return trim(preg_replace('/\s+/', ' ', str_replace(':stage', $stage, $purpose)));
    }

    /**
     * Rentang tanggal penugasan sebagaimana tercetak: "09 – 10 Februari 2026".
     */
    public function assignmentDateText(?\DateTimeInterface $start, ?\DateTimeInterface $end): string
    {
        if (! $start && ! $end) {
            return '';
        }

        $start = $start ? Carbon::instance(Carbon::parse($start)) : null;
        $end = $end ? Carbon::instance(Carbon::parse($end)) : null;

        if (! $start) {
            return $this->longDate($end);
        }

        if (! $end || $start->isSameDay($end)) {
            return $this->longDate($start);
        }

        // Bulan dan tahun cukup ditulis sekali bila rentangnya dalam bulan sama.
        if ($start->isSameMonth($end) && $start->year === $end->year) {
            return $start->format('d').' – '.$this->longDate($end);
        }

        return $this->longDate($start).' – '.$this->longDate($end);
    }

    public function longDate(?\DateTimeInterface $date): string
    {
        if (! $date) {
            return '';
        }

        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $date = Carbon::parse($date);

        return $date->format('d').' '.$months[(int) $date->format('n')].' '.$date->format('Y');
    }

    /**
     * Nilai pertama yang terisi dari beberapa kode field klien.
     */
    private function pick(CertificationApplication $application, array $codes): string
    {
        foreach ($codes as $code) {
            $flat = $this->flatten($application->value($code));
            if ($flat !== '') {
                return $flat;
            }
        }

        return '';
    }

    private function phoneFax(CertificationApplication $application): string
    {
        $phone = $this->pick($application, ['phone']) ?: (string) ($application->contact_phone ?? '');
        $fax = $this->pick($application, ['fax']);

        return $this->joinFilled([$phone, $fax], ' / ') ?: '-';
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinFilled(array $parts, string $glue): string
    {
        return implode($glue, array_filter(array_map('trim', $parts), fn ($part) => $part !== ''));
    }

    /**
     * Ratakan nilai isian klien menjadi satu baris teks.
     *
     * Field bertipe JSON (daftar site, merek, kelompok centang ISPO) kembali
     * sebagai larik — bahkan larik bersarang. Tanpa perataan ini surat akan
     * mencetak "Array" atau memicu galat di penggambar PDF.
     */
    private function flatten(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $parts = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                // Baris tabel/repeatable: gabungkan sel yang terisi.
                $cells = array_filter(array_map(
                    fn ($cell) => is_scalar($cell) ? trim((string) $cell) : '',
                    $item
                ), fn ($cell) => $cell !== '');

                if ($cells !== []) {
                    $parts[] = implode(' ', $cells);
                }

                continue;
            }

            if (is_scalar($item) && trim((string) $item) !== '') {
                $parts[] = trim((string) $item);
            }
        }

        return implode(', ', $parts);
    }
}
