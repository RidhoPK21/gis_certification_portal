<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AssignmentLetter;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\User;
use App\Services\AssignmentLetterPdfService;
use App\Services\AssignmentLetterService;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use App\Services\ReviewPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Surat Tugas auditor: pemantauan order pasca-pembayaran dan penerbitan surat
 * per tahap audit oleh Tim Teknis. Auditor tidak dapat bekerja pada sebuah
 * tahap sampai suratnya terbit.
 */
class AssignmentLetterController extends Controller
{
    /**
     * Order yang dipantau Tim Teknis di halaman ini: sejak pembayaran lunas
     * sampai proses selesai, agar surat lama tetap bisa dilihat dan dicetak ulang.
     */
    private const MONITORED_STATUSES = [
        'payment_completed', 'stage_1_audit', 'stage_2_audit', 'qms_audit',
        'corrective_action', 'corrective_revision', 'certificate_review',
        'final_certificate', 'completed', 'surveillance',
    ];

    public function index(Request $request)
    {
        $query = CertificationApplication::whereIn('status', self::MONITORED_STATUSES)
            ->handledBy($request->user())
            ->with(['scheme', 'client', 'auditAssignments.auditor', 'assignmentLetters.generatedPdf'])
            ->latest('updated_at');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q): void {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        // Saring order yang suratnya untuk tahap tertentu belum terbit.
        if ($request->filled('stage') && array_key_exists($request->string('stage')->toString(), AssignmentLetter::STAGES)) {
            $stage = $request->string('stage')->toString();
            $query->whereDoesntHave('assignmentLetters', function ($sub) use ($stage): void {
                $sub->where('stage_code', $stage)->whereNotNull('generated_pdf_id');
            });
        }

        return view('internal.technical.assignments', [
            'applications' => $query->paginate(20)->withQueryString(),
            'schemes' => CertificationScheme::orderBy('sort_order')->get(),
            'stages' => AssignmentLetter::STAGES,
        ]);
    }

    public function show(CertificationApplication $application, AssignmentLetterService $letters)
    {
        $this->ensureMonitored($application);

        $application->load([
            'scheme', 'client', 'values', 'auditAssignments.auditor',
            'assignmentLetters.generatedPdf', 'generatedPdfs', 'reviews',
        ]);

        $family = $letters->family($application);
        $existing = $application->assignmentLetters->keyBy('stage_code');

        // Nilai awal per tahap: pakai yang tersimpan bila ada, kalau belum ambil
        // prefill dari jawaban klien dan penugasan auditor terkini.
        $drafts = [];
        foreach (array_keys(AssignmentLetter::STAGES) as $stage) {
            $letter = $existing->get($stage);
            $suggested = $letters->suggestNumber($family, now());

            $drafts[$stage] = [
                'letter' => $letter,
                'number' => $letter?->letter_number ?: $suggested['number'],
                'sequence' => $letter?->sequence_number ?: $suggested['sequence'],
                'place' => $letter?->letter_place ?: config('assignment_letter.default_place'),
                'overrides' => array_merge(
                    $letters->defaults($application, $stage),
                    (array) ($letter?->field_overrides ?? [])
                ),
                'auditors' => $letter?->auditor_rows ?: $letters->auditorRows($application, $stage),
                'current_auditors' => $letters->auditorRows($application, $stage),
            ];
        }

        return view('internal.technical.assignment-letter', [
            'application' => $application,
            'family' => $family,
            'template' => $letters->templateConfig($application),
            'stages' => AssignmentLetter::STAGES,
            'drafts' => $drafts,
            'auditors' => User::where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('code', 'auditor'))
                ->orderBy('name')
                ->get(),
            'panelistCandidates' => User::where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('code', config('review.panelist_roles')))
                ->orderBy('name')
                ->get(),
            'technicalReview' => $application->reviews->where('review_type', 'technical')->sortByDesc('round')->first(),
            // Surat lain pada order ini yang tanda tangan berstempelnya bisa dipakai ulang.
            'reusableSignature' => $application->assignmentLetters
                ->whereNotNull('signature_path')
                ->sortByDesc('signature_uploaded_at')
                ->first(),
        ]);
    }

    public function save(Request $request, CertificationApplication $application, string $stage, AssignmentLetterService $letters, AuditLogger $audit)
    {
        $this->ensureMonitored($application);
        $this->ensureStage($stage);

        $letter = $this->persist($request, $application, $stage, $letters);
        $audit->log('assignment_letter.saved', $letter, [], ['application_id' => $application->id, 'stage' => $stage]);

        return back()->with('success', 'Draft Surat Tugas '.AssignmentLetter::STAGES[$stage].' tersimpan.');
    }

    public function generate(Request $request, CertificationApplication $application, string $stage, AssignmentLetterService $letters, AssignmentLetterPdfService $pdfs)
    {
        $this->ensureMonitored($application);
        $this->ensureStage($stage);

        $letter = $this->persist($request, $application, $stage, $letters);

        abort_if(
            ($letter->auditor_rows ?? []) === [],
            422,
            'Surat Tugas tidak dapat diterbitkan tanpa auditor. Tentukan tim auditor lebih dahulu.'
        );

        $record = $pdfs->generate($letter, $request->user()->id);

        return redirect()
            ->route('technical.assignments.show', $application)
            ->with('success', 'Surat Tugas '.AssignmentLetter::STAGES[$stage].' versi '.$record->document_version.' diterbitkan.');
    }

    /**
     * Tanda tangan berstempel untuk Surat Tugas tahap ini.
     *
     * Berbeda dari PDF tinjauan yang memakai tanda tangan elektronik dari
     * profil akun: surat resmi ini dibubuhi hasil pindaian tanda tangan
     * berstempel yang diunggah Tim Teknis.
     *
     * Dengan reuse_from_previous, berkas dari surat tahap lain pada order yang
     * sama disalin — bukan dipakai bersama — agar mengganti berkas di satu
     * tahap tidak mengubah PDF tahap lain yang sudah terbit.
     */
    public function uploadSignature(Request $request, CertificationApplication $application, string $stage, AssignmentLetterService $letters, FileStorageService $files, AuditLogger $audit)
    {
        $this->ensureMonitored($application);
        $this->ensureStage($stage);

        $reuse = $request->boolean('reuse_from_previous');

        $request->validate([
            'signature' => [$reuse ? 'nullable' : 'required', 'file'],
        ]);

        $letter = AssignmentLetter::firstOrNew([
            'application_id' => $application->id,
            'stage_code' => $stage,
            'cycle' => 0,
        ]);

        if (! $letter->exists) {
            $family = $letters->family($application);
            $suggested = $letters->suggestNumber($family, now());

            $letter->fill([
                'template_code' => $application->scheme->review_template,
                'number_family' => $family,
                'letter_number' => $suggested['number'],
                'sequence_number' => $suggested['sequence'],
                'letter_place' => config('assignment_letter.default_place'),
                'letter_date' => now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);
        }

        $directory = 'applications/'.$application->id.'/assignment-letters/signatures';

        if ($reuse) {
            $source = AssignmentLetter::where('application_id', $application->id)
                ->whereNotNull('signature_path')
                ->where('id', '!=', $letter->id ?? 0)
                ->orderByDesc('signature_uploaded_at')
                ->first();

            abort_unless($source, 422, 'Belum ada tanda tangan berstempel pada tahap lain di order ini.');
            abort_unless(Storage::disk('private')->exists($source->signature_path), 422, 'Berkas tanda tangan tahap sebelumnya tidak ditemukan.');

            $extension = pathinfo($source->signature_path, PATHINFO_EXTENSION) ?: 'jpg';
            $path = $directory.'/'.$stage.'_'.now()->format('YmdHis').'_'.Str::random(6).'.'.$extension;
            Storage::disk('private')->copy($source->signature_path, $path);

            $letter->fill([
                'signature_path' => $path,
                'signature_original_name' => $source->signature_original_name,
                'signature_mime' => $source->signature_mime,
                'signature_size_bytes' => $source->signature_size_bytes,
                'signature_uploaded_by' => $request->user()->id,
                'signature_uploaded_at' => now(),
                'updated_by' => $request->user()->id,
            ])->save();

            $audit->log('assignment_letter.signature_reused', $letter, [], ['application_id' => $application->id, 'stage' => $stage]);

            return back()->with('success', 'Tanda tangan berstempel dari tahap sebelumnya disalin ke surat ini.');
        }

        $file = $request->file('signature');
        $files->validate($file);

        // SimplePdf menggambar JPEG; PNG dikonversi saat render. Format lain ditolak.
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(
            in_array($extension, (array) config('assignment_letter.signature_extensions'), true),
            422,
            'Tanda tangan berstempel harus berupa gambar JPG atau PNG.'
        );

        $maxBytes = ((int) config('assignment_letter.signature_max_mb', 5)) * 1024 * 1024;
        abort_if($file->getSize() > $maxBytes, 422, 'Ukuran gambar tanda tangan melebihi batas '.config('assignment_letter.signature_max_mb').' MB.');

        $path = $file->storeAs(
            $directory,
            $stage.'_'.now()->format('YmdHis').'_'.Str::random(6).'.'.$extension,
            'private'
        );

        $letter->fill([
            'signature_path' => $path,
            'signature_original_name' => $file->getClientOriginalName(),
            'signature_mime' => $file->getMimeType(),
            'signature_size_bytes' => $file->getSize(),
            'signature_uploaded_by' => $request->user()->id,
            'signature_uploaded_at' => now(),
            'updated_by' => $request->user()->id,
        ])->save();

        $audit->log('assignment_letter.signature_uploaded', $letter, [], [
            'application_id' => $application->id,
            'stage' => $stage,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', 'Tanda tangan berstempel tersimpan. Terbitkan ulang surat agar tercetak.');
    }

    /**
     * Generate ulang PDF tinjauan setelah tim auditor atau panelis diganti,
     * supaya dokumen tinjauan mencerminkan tim yang benar-benar bertugas.
     */
    public function regenerateReview(Request $request, CertificationApplication $application, ReviewPdfService $pdfs)
    {
        $this->ensureMonitored($application);

        $record = $pdfs->generate($application, $request->user()->id);

        return redirect()
            ->route('technical.assignments.show', $application)
            ->with('success', 'PDF tinjauan digenerate ulang menjadi versi '.$record->document_version.'.');
    }

    /**
     * Simpan masukan surat, membuat barisnya bila belum ada.
     */
    private function persist(Request $request, CertificationApplication $application, string $stage, AssignmentLetterService $letters): AssignmentLetter
    {
        $family = $letters->family($application);
        $config = config('assignment_letter.templates.'.$family);
        $allowedKeys = array_keys(array_merge($config['primary_fields'], $config['detail_fields']));

        $data = $request->validate([
            'letter_number' => ['required', 'string', 'max:100'],
            'letter_place' => ['required', 'string', 'max:80'],
            'letter_date' => ['required', 'date'],
            'assignment_start_date' => ['nullable', 'date'],
            'assignment_end_date' => ['nullable', 'date', 'after_or_equal:assignment_start_date'],
            'signer_name' => ['nullable', 'string', 'max:150'],
            'signer_position' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string', 'max:2000'],
            'auditors' => ['nullable', 'array'],
            'auditors.*.auditor_id' => ['required', 'integer'],
            'auditors.*.include' => ['nullable'],
            'auditors.*.name' => ['required', 'string', 'max:150'],
            'auditors.*.role_code' => ['nullable', 'string', 'max:10'],
            'auditors.*.position_label' => ['nullable', 'string', 'max:120'],
        ]);

        // Hanya kunci yang memang milik template ini yang disimpan.
        $overrides = array_intersect_key($data['fields'] ?? [], array_flip($allowedKeys));
        $overrides = array_map(fn ($value) => trim((string) $value), $overrides);

        $rows = collect($data['auditors'] ?? [])
            ->filter(fn ($row) => filled($row['include'] ?? null))
            ->values()
            ->map(fn ($row, $index) => [
                'auditor_id' => (int) $row['auditor_id'],
                'name' => trim((string) $row['name']),
                'role_code' => (string) ($row['role_code'] ?? ''),
                'position_label' => trim((string) ($row['position_label'] ?? '')) ?: (config('assignment_letter.role_labels')[$row['role_code'] ?? ''] ?? ''),
                'sort' => $index + 1,
            ])
            ->all();

        $existing = AssignmentLetter::where('application_id', $application->id)
            ->where('stage_code', $stage)
            ->where('cycle', 0)
            ->first();

        $attributes = [
            'template_code' => $application->scheme->review_template,
            'number_family' => $family,
            'letter_number' => $data['letter_number'],
            'letter_place' => $data['letter_place'],
            'letter_date' => $data['letter_date'],
            'assignment_start_date' => $data['assignment_start_date'] ?? null,
            'assignment_end_date' => $data['assignment_end_date'] ?? null,
            'auditor_rows' => $rows,
            'field_overrides' => $overrides,
            'signer_name' => $data['signer_name'] ?? $request->user()->name,
            'signer_position' => $data['signer_position'] ?? $config['default_signer_position'],
            'signed_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ];

        if (! $existing) {
            $attributes['sequence_number'] = $this->sequenceFor($family, $data['letter_number'], $data['letter_date']);
            $attributes['created_by'] = $request->user()->id;

            return AssignmentLetter::create($attributes + [
                'application_id' => $application->id,
                'stage_code' => $stage,
                'cycle' => 0,
            ]);
        }

        // Nomor yang diedit manual ikut memperbarui urutan agar saran berikutnya
        // tidak menabrak nomor yang sudah dipakai.
        if ($existing->letter_number !== $data['letter_number']) {
            $attributes['sequence_number'] = $this->sequenceFor($family, $data['letter_number'], $data['letter_date']);
        }

        $existing->update($attributes);

        return $existing->refresh();
    }

    /**
     * Angka urut di depan nomor surat, dipakai untuk menyarankan nomor berikutnya.
     */
    private function sequenceFor(string $family, string $number, string $date): int
    {
        if (preg_match('/^\s*(\d+)/', $number, $match)) {
            return (int) $match[1];
        }

        return app(AssignmentLetterService::class)->suggestNumber($family, new \DateTime($date))['sequence'];
    }

    private function ensureMonitored(CertificationApplication $application): void
    {
        abort_unless(
            in_array($application->status, self::MONITORED_STATUSES, true),
            422,
            'Surat Tugas baru dapat disiapkan setelah pembayaran diselesaikan Finance.'
        );
    }

    private function ensureStage(string $stage): void
    {
        abort_unless(array_key_exists($stage, AssignmentLetter::STAGES), 404);
    }
}
