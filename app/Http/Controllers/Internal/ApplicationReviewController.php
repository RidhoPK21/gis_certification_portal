<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\ApplicationRevisionItem;
use App\Models\CertificationApplication;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\IspoReviewService;
use App\Services\PortalNotificationService;
use App\Services\ReviewPdfService;
use App\Services\ReviewService;
use App\Services\RevisionRequestService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationReviewController extends Controller
{
    public function index(Request $request)
    {
        // Tiap tim hanya melihat skema yang menjadi tanggung jawabnya.
        $query = CertificationApplication::with(['scheme', 'client'])
            ->handledBy($request->user())
            ->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('order_number', 'like', '%'.$request->q.'%')
                ->orWhere('company_name', 'like', '%'.$request->q.'%'));
        }
        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('internal.applications.index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'schemes' => \App\Models\CertificationScheme::orderBy('sort_order')->get(),
        ]);
    }

    public function show(CertificationApplication $application, DynamicFormService $forms, ReviewService $reviews)
    {
        // Catatan: relasi sertifikat/surveillance ditambahkan pada Fase 7-8.
        $application->load([
            'scheme.sections.fields.options', 'scheme.requiredDocuments', 'client', 'values',
            'documents.currentVersion', 'documents.versions', 'revisions', 'reviews.items',
            'statusHistory', 'generatedPdfs', 'auditAssignments.auditor',
        ]);
        $application->setRelation('scheme', $forms->schemeForApplication($application));

        /*
         * Admin hanya mengkaji dokumen administrasi; dokumen ber-review_group
         * 'technical' dinilai Tim Teknis pada tahap tinjauan teknis. values()
         * wajib agar indeks items[] pada form tetap rapat mulai dari nol.
         */
        $adminReview = $application->reviews->where('review_type', 'administration')->sortByDesc('round')->first();

        /*
         * ISPO memakai formulir FrO.7204 yang bentuknya berbeda: bagian 1-3
         * dikerjakan Admin dengan kolom bercentang per ruang lingkup pemohon.
         */
        $ispo = app(IspoReviewService::class);
        $isIspo = $ispo->isIspo($application);

        return view('internal.applications.show', [
            'application' => $application,
            // Baris tabel mengikuti formulir tinjauan, bukan seluruh checklist klien.
            'adminDocuments' => $reviews->formRows($application, 'administration'),
            'technicalDocuments' => $reviews->formRows($application, 'technical'),
            'adminReview' => $adminReview,
            // Dipakai untuk mengisi ulang dropdown Keterangan*) FrM.9107.
            'adminReviewItems' => $adminReview?->items->keyBy('item_code') ?? collect(),
            'isIspo' => $isIspo,
            'ispoGroups' => $isIspo ? $ispo->groupedRows($application, 'administration') : [],
            'ispoSaved' => $isIspo ? $ispo->savedItems($application, 'administration') : [],
            'ispoScopeLabel' => $isIspo ? $this->ispoScopeLabel($ispo, $application) : '',
        ]);
    }

    /**
     * Ringkasan ruang lingkup yang dipilih pemohon, untuk menjelaskan kepada
     * peninjau mengapa hanya sebagian kelompok checklist yang ditampilkan.
     */
    private function ispoScopeLabel(IspoReviewService $ispo, CertificationApplication $application): string
    {
        $labels = [
            'pekebun_perorangan' => 'Pekebun Perorangan',
            'kelompok_pekebun' => 'Kelompok Pekebun',
            'perusahaan_perkebunan' => 'Perusahaan Perkebunan',
            'industri_hilir' => 'Industri Hilir',
            'perusahaan_bioenergi' => 'Usaha Bioenergi',
        ];

        $scopes = array_map(
            fn ($code) => $labels[$code] ?? $code,
            $ispo->applicantScopes($application)
        );

        return $scopes ? implode(', ', $scopes) : 'belum dipilih pemohon — seluruh kelompok ditampilkan';
    }

    public function saveReview(Request $request, CertificationApplication $application, AuditLogger $audit, ReviewService $reviews)
    {
        // Bagian teknis kini diisi oleh Tim Teknis (TechnicalController), bukan admin.
        $data = $request->validate([
            'review_type' => ['required', Rule::in(['administration'])],
            // signed_name diabaikan bila dikirim: nilainya diambil dari akun peninjau.
            'notes' => ['nullable', 'string'], 'action_date' => ['required', 'date'], 'signed_name' => ['nullable', 'string', 'max:150'],
            'site_count' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'items' => ['nullable', 'array'], 'items.*.type' => ['required_with:items', 'string'], 'items.*.code' => ['required_with:items', 'string'],
            'items.*.label' => ['required_with:items', 'string'], 'items.*.presence' => ['nullable', 'string'],
            'items.*.status' => ['required_with:items', Rule::in(ReviewService::STATUSES)],
            'items.*.remark_option' => ['nullable', Rule::in(ReviewService::REMARK_OPTIONS)],
            'items.*.remark_date' => ['nullable', 'date', 'required_if:items.*.remark_option,tgl_berlaku'],
            'items.*.notes' => ['nullable', 'string'],
            // Isian bagian 1 FrO.7204 (khusus ISPO).
            'ispo' => ['nullable', 'array'],
            'ispo.documents_received_at' => ['nullable', 'date'],
            'ispo.initial_completeness' => ['nullable', Rule::in(['lengkap', 'perlu_dilengkapi'])],
            'ispo.administrative_notes' => ['nullable', 'string'],
        ], [
            'items.*.remark_date.required_if' => 'Tanggal berlaku wajib diisi bila keterangan memilih "Tgl Berlaku".',
        ]);

        /*
         * Baris FrO.7204 bukan dokumen checklist skema, jadi pagar kode dokumen
         * tidak berlaku — kodenya milik formulir tinjauan ISPO sendiri.
         */
        $documentGroup = app(IspoReviewService::class)->isIspo($application) ? null : 'administration';

        $review = $reviews->save($application, $data['review_type'], $data, $request->user()->id, $documentGroup);
        $audit->log('application.review_saved', $review, [], ['application_id' => $application->id, 'type' => $data['review_type']]);

        return back()->with('success', 'Hasil kajian administrasi berhasil disimpan.');
    }

    public function forwardToTechnical(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        abort_unless($application->status === 'admin_review', 422, 'Permohonan tidak berada pada tahap review admin.');
        $hasAdminReview = $application->reviews()->where('review_type', 'administration')->exists();
        abort_unless($hasAdminReview, 422, 'Simpan kajian administrasi terlebih dahulu sebelum meneruskan ke Tim Teknis.');
        $open = $application->revisions()->whereIn('status', ['open', 'submitted'])->count();
        abort_if($open > 0, 422, 'Masih ada item revisi terbuka. Tutup item sebelum meneruskan ke Tim Teknis.');

        // Bila diteruskan ulang untuk koreksi, tinjauan teknis harus diselesaikan
        // kembali oleh Tim Teknis sebelum admin dapat menyetujui.
        $application->reviews()->where('review_type', 'technical')->whereNotNull('completed_at')->update(['completed_at' => null]);

        $workflow->transition($application, 'technical_review', 'admin_forward_technical', 'Diteruskan ke Tim Teknis untuk tinjauan teknis.', $request->user()->id);
        $notifications->sendToSchemeOwner($application, 'technical', 'technical_review_pending', 'Tinjauan Teknis Baru', 'Permohonan '.$application->order_number.' menunggu tinjauan teknis.', route('technical.reviews.show', $application));
        $audit->log('application.forwarded_technical', $application, [], ['application_id' => $application->id]);

        return back()->with('success', 'Permohonan diteruskan ke Tim Teknis untuk tinjauan teknis.');
    }

    public function requestRevision(Request $request, CertificationApplication $application, RevisionRequestService $revisions)
    {
        abort_unless($application->status === 'admin_review', 422, 'Revisi hanya dapat diminta saat review admin.');
        $data = $request->validate(RevisionRequestService::rules(), RevisionRequestService::messages());

        $revisions->request(
            $application,
            $data['targets'],
            $data['due_date'] ?? null,
            $request->user(),
            'admin_request_revision',
            'Admin'
        );

        return back()->with('success', 'Permintaan revisi telah dikirim ke klien.');
    }

    public function resolveRevision(Request $request, CertificationApplication $application, ApplicationRevisionItem $revision, RevisionRequestService $revisions)
    {
        abort_unless($revision->application_id === $application->id, 404);
        abort_if($revision->status === 'resolved', 422, 'Item revisi sudah ditutup.');
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:2000']]);

        $revisions->resolve($revision, $data['resolution_note'], $request->user());

        return back()->with('success', 'Item revisi ditandai selesai.');
    }

    public function generatePdf(Request $request, CertificationApplication $application, ReviewPdfService $service)
    {
        $record = $service->generate($application, $request->user()->id);

        return redirect()->route('internal.generated-pdf.download', $record);
    }

    public function updateOrder(Request $request, CertificationApplication $application, AuditLogger $audit)
    {
        $data = $request->validate(['order_number' => ['required', 'string', 'max:100', Rule::unique('applications', 'order_number')->ignore($application->id)], 'order_date' => ['required', 'date'], 'reason' => ['required', 'string']]);
        $old = $application->only(['order_number', 'order_date']);
        $application->update(['order_number' => $data['order_number'], 'order_date' => $data['order_date'], 'updated_by' => $request->user()->id]);
        $audit->log('application.order_number_changed', $application, $old, $application->only(['order_number', 'order_date']), ['reason' => $data['reason']]);

        return back()->with('success', 'Nomor dan tanggal order diperbarui. Perubahan tercatat di audit trail.');
    }
}
