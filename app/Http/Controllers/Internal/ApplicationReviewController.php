<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\ApplicationRevisionItem;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\IspoReviewService;
use App\Services\PortalNotificationService;
use App\Services\ReviewPdfService;
use App\Services\ReviewService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificationApplication::with(['scheme', 'client'])->latest();
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
        $auditors = User::where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'auditor'))
            ->orderBy('name')
            ->get();

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
            'auditors' => $auditors,
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

    public function assignAuditor(Request $request, CertificationApplication $application, AuditLogger $audit, PortalNotificationService $notifications)
    {
        $data = $request->validate([
            'auditor_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_role' => ['required', Rule::in(['LA', 'A', 'TA'])],
            'stage_code' => ['required', Rule::in(['all', 'stage_1', 'stage_2', 'qms', 'corrective_action'])],
            'assigned_date' => ['required', 'date'],
        ]);
        $auditor = User::whereKey($data['auditor_id'])->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'auditor'))->firstOrFail();
        $assignment = AuditAssignment::updateOrCreate(
            ['application_id' => $application->id, 'auditor_id' => $auditor->id, 'stage_code' => $data['stage_code']],
            ['assignment_role' => $data['assignment_role'], 'assigned_date' => $data['assigned_date'], 'status' => 'assigned', 'assigned_by' => $request->user()->id]
        );
        $audit->log('audit.assignment_saved', $assignment, [], ['auditor' => $auditor->email]);
        $notifications->send($auditor, 'auditor_assigned', 'Tugas Audit Baru', 'Anda ditugaskan sebagai ' . $data['assignment_role'] . ' untuk order ' . $application->order_number . '.', route('audit.show', $application));

        return back()->with('success', 'Auditor berhasil ditugaskan ke order ini.');
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
        $notifications->sendToRole('technical', 'technical_review_pending', 'Tinjauan Teknis Baru', 'Permohonan '.$application->order_number.' menunggu tinjauan teknis.', route('technical.reviews.show', $application));
        $audit->log('application.forwarded_technical', $application, [], ['application_id' => $application->id]);

        return back()->with('success', 'Permohonan diteruskan ke Tim Teknis untuk tinjauan teknis.');
    }

    public function requestRevision(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        abort_unless($application->status === 'admin_review', 422, 'Revisi hanya dapat diminta saat review admin.');
        $data = $request->validate([
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.type' => ['required', Rule::in(['field', 'document'])],
            'targets.*.code' => ['required', 'string'],
            'targets.*.label' => ['required', 'string'],
            'targets.*.note' => ['required', 'string'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'targets.required' => 'Pilih minimal satu item (kolom atau dokumen) yang perlu direvisi dengan mencentang kotak revisi.',
            'targets.min' => 'Pilih minimal satu item (kolom atau dokumen) yang perlu direvisi dengan mencentang kotak revisi.',
            'targets.*.note.required' => 'Catatan revisi wajib diisi untuk setiap item yang dipilih.',
        ]);
        $round = ((int) $application->revisions()->max('revision_round')) + 1;
        foreach ($data['targets'] as $target) {
            ApplicationRevisionItem::create(['application_id' => $application->id, 'revision_round' => $round, 'target_type' => $target['type'], 'target_code' => $target['code'], 'target_label' => $target['label'], 'revision_note' => $target['note'], 'due_date' => $data['due_date'] ?? null, 'requested_by' => $request->user()->id]);
        }
        $workflow->transition($application, 'revision_requested', 'admin_request_revision', 'Admin meminta revisi spesifik pada '.count($data['targets']).' item.', $request->user()->id, null, ['round' => $round]);
        $notifications->send($application->client_id, 'revision_requested', 'Perbaikan permohonan diperlukan', 'Tim GIS meminta perbaikan pada '.count($data['targets']).' item untuk order '.$application->order_number.'.', route('client.applications.edit', $application), ['round' => $round]);
        $audit->log('application.revision_requested', $application, [], ['round' => $round, 'targets' => $data['targets']]);

        return back()->with('success', 'Permintaan revisi telah dikirim ke klien.');
    }

    public function resolveRevision(Request $request, CertificationApplication $application, ApplicationRevisionItem $revision, AuditLogger $audit)
    {
        abort_unless($revision->application_id === $application->id, 404);
        abort_if($revision->status === 'resolved', 422, 'Item revisi sudah ditutup.');
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:2000']]);
        $old = $revision->toArray();
        $revision->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);
        $audit->log('application.revision_resolved', $revision, $old, $revision->fresh()->toArray(), ['resolution_note' => $data['resolution_note']]);

        return back()->with('success', 'Item revisi ditandai selesai.');
    }

    public function approve(Request $request, CertificationApplication $application, WorkflowService $workflow, ReviewPdfService $pdfs, PortalNotificationService $notifications)
    {
        abort_unless($application->status === 'admin_review', 422);
        $data = $request->validate(['notes' => ['nullable', 'string'], 'action_date' => ['required', 'date']]);
        $open = $application->revisions()->whereIn('status', ['open', 'submitted'])->count();
        abort_if($open > 0, 422, 'Masih ada item revisi terbuka. Tutup item sebelum menyetujui.');
        $technicalDone = $application->reviews()->where('review_type', 'technical')->whereNotNull('completed_at')->exists();
        abort_unless($technicalDone, 422, 'Tinjauan teknis belum selesai oleh Tim Teknis. Teruskan ke Tim Teknis lebih dahulu.');
        // Menyetujui permohonan = kedua bagian kajian (administrasi & teknis) diterima.
        foreach (['administration', 'technical'] as $type) {
            $review = $application->reviews()->where('review_type', $type)->latest()->first();
            if ($review) {
                $review->update(['status' => 'approved', 'completed_at' => now(), 'action_date' => $data['action_date'], 'notes' => $data['notes'] ?? $review->notes]);
            }
        }
        $application = $workflow->transition($application, 'application_approved', 'admin_approve', $data['notes'] ?? 'Permohonan disetujui.', $request->user()->id, new \DateTime($data['action_date']));
        $application->update(['approved_at' => now()]);
        $pdfs->generate($application, $request->user()->id);
        $application = $workflow->transition($application->refresh(), 'invoice_process', 'open_finance', 'Permohonan diteruskan ke Finance.', $request->user()->id);
        $notifications->send($application->client_id, 'application_approved', 'Permohonan disetujui', 'Permohonan '.$application->order_number.' disetujui dan masuk proses invoice.', route('client.applications.show', $application));
        $notifications->sendToRole('finance', 'invoice_process', 'Order Baru untuk Invoice', 'Permohonan '.$application->order_number.' telah disetujui dan diteruskan untuk pembuatan invoice.', route('finance.show', $application));

        return back()->with('success', 'Permohonan disetujui, PDF tinjauan dibuat, dan order diteruskan ke Finance.');
    }

    public function reject(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications)
    {
        abort_unless($application->status === 'admin_review', 422);
        $data = $request->validate(['reason' => ['required', 'string'], 'action_date' => ['required', 'date']]);
        // Menolak permohonan = kedua bagian kajian (administrasi & teknis) ditolak.
        foreach (['administration', 'technical'] as $type) {
            $review = $application->reviews()->where('review_type', $type)->latest()->first();
            if ($review) {
                $review->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'completed_at' => now(), 'action_date' => $data['action_date']]);
            }
        }
        $workflow->transition($application, 'rejected', 'admin_reject', $data['reason'], $request->user()->id, new \DateTime($data['action_date']));
        $notifications->send($application->client_id, 'application_rejected', 'Permohonan ditolak', 'Permohonan '.$application->order_number.' tidak dapat dilanjutkan. Lihat alasan pada dashboard.', route('client.applications.show', $application));

        return back()->with('success', 'Keputusan penolakan tersimpan dan klien telah diberi notifikasi.');
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
