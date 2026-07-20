<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\ApplicationReview;
use App\Models\ApplicationRevisionItem;
use App\Models\CertificationApplication;
use App\Models\ReviewFormItem;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\PortalNotificationService;
use App\Services\ReviewPdfService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('internal.applications.index', ['applications' => $query->paginate(20)->withQueryString()]);
    }

    public function show(CertificationApplication $application, DynamicFormService $forms)
    {
        // Catatan: relasi audit/invoice/sertifikat ditambahkan pada Fase 5-7.
        $application->load([
            'scheme.sections.fields.options', 'scheme.requiredDocuments', 'client', 'values',
            'documents.currentVersion', 'documents.versions', 'revisions', 'reviews.items',
            'statusHistory', 'generatedPdfs',
        ]);
        $application->setRelation('scheme', $forms->schemeForApplication($application));

        return view('internal.applications.show', compact('application'));
    }

    public function saveReview(Request $request, CertificationApplication $application, AuditLogger $audit)
    {
        $data = $request->validate([
            'review_type' => ['required', Rule::in(['administration', 'technical'])],
            'notes' => ['nullable', 'string'], 'action_date' => ['required', 'date'], 'signed_name' => ['required', 'string', 'max:150'],
            'items' => ['nullable', 'array'], 'items.*.type' => ['required_with:items', 'string'], 'items.*.code' => ['required_with:items', 'string'],
            'items.*.label' => ['required_with:items', 'string'], 'items.*.presence' => ['nullable', 'string'],
            'items.*.status' => ['required_with:items', Rule::in(['pending', 'sufficient', 'insufficient', 'meets', 'not_meets'])],
            'items.*.notes' => ['nullable', 'string'],
        ]);
        $review = DB::transaction(function () use ($application, $request, $data) {
            $round = ((int) $application->reviews()->where('review_type', $data['review_type'])->max('round')) ?: 1;
            $review = ApplicationReview::updateOrCreate(
                ['application_id' => $application->id, 'review_type' => $data['review_type'], 'round' => $round, 'status' => 'in_progress'],
                ['notes' => $data['notes'] ?? null, 'action_date' => $data['action_date'], 'signed_name' => $data['signed_name'], 'reviewed_by' => $request->user()->id]
            );
            foreach ($data['items'] ?? [] as $index => $item) {
                ReviewFormItem::updateOrCreate(
                    ['application_review_id' => $review->id, 'item_type' => $item['type'], 'item_code' => $item['code']],
                    ['item_label' => $item['label'], 'presence_status' => $item['presence'] ?? null, 'review_status' => $item['status'], 'notes' => $item['notes'] ?? null, 'sort_order' => $index]
                );
                if ($item['type'] === 'document') {
                    $application->documents()->where('document_code', $item['code'])->update(['review_status' => $item['status'], 'review_note' => $item['notes'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                }
            }

            return $review;
        });
        $audit->log('application.review_saved', $review, [], ['application_id' => $application->id, 'type' => $data['review_type']]);

        return back()->with('success', 'Hasil kajian '.$data['review_type'].' berhasil disimpan.');
    }

    public function requestRevision(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        abort_unless($application->status === 'admin_review', 422, 'Revisi hanya dapat diminta saat review admin.');
        $data = $request->validate(['targets' => ['required', 'array', 'min:1'], 'targets.*.type' => ['required', Rule::in(['field', 'document'])], 'targets.*.code' => ['required', 'string'], 'targets.*.label' => ['required', 'string'], 'targets.*.note' => ['required', 'string'], 'due_date' => ['nullable', 'date', 'after_or_equal:today']]);
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
        $review = $application->reviews()->where('review_type', 'administration')->latest()->first();
        if ($review) {
            $review->update(['status' => 'approved', 'completed_at' => now(), 'action_date' => $data['action_date'], 'notes' => $data['notes'] ?? $review->notes]);
        }
        $application = $workflow->transition($application, 'application_approved', 'admin_approve', $data['notes'] ?? 'Permohonan disetujui.', $request->user()->id, new \DateTime($data['action_date']));
        $application->update(['approved_at' => now()]);
        $pdfs->generate($application, $request->user()->id);
        $application = $workflow->transition($application->refresh(), 'invoice_process', 'open_finance', 'Permohonan diteruskan ke Finance.', $request->user()->id);
        $notifications->send($application->client_id, 'application_approved', 'Permohonan disetujui', 'Permohonan '.$application->order_number.' disetujui dan masuk proses invoice.', route('client.applications.show', $application));

        return back()->with('success', 'Permohonan disetujui, PDF tinjauan dibuat, dan order diteruskan ke Finance.');
    }

    public function reject(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications)
    {
        abort_unless($application->status === 'admin_review', 422);
        $data = $request->validate(['reason' => ['required', 'string'], 'action_date' => ['required', 'date']]);
        $review = $application->reviews()->where('review_type', 'administration')->latest()->first();
        if ($review) {
            $review->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'completed_at' => now(), 'action_date' => $data['action_date']]);
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
