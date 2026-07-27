<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Services\ApplicationSubmissionService;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        return view('client.applications.index', [
            'applications' => CertificationApplication::where('client_id', $request->user()->id)
                ->with('scheme')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function schemes()
    {
        return view('client.applications.schemes', [
            'schemes' => CertificationScheme::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(CertificationScheme $scheme)
    {
        abort_unless($scheme->is_active, 404);

        return view('client.applications.create', ['scheme' => $scheme]);
    }

    public function store(Request $request, CertificationScheme $scheme, ApplicationSubmissionService $service)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'applicant_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['required', 'string', 'max:30'],
        ]);

        $data['form_version'] = $scheme->form_version;
        $app = $service->createDraft($request->user()->id, $scheme->id, $data);

        return redirect()->route('client.applications.edit', $app)
            ->with('success', 'Draft permohonan dibuat. Isi setiap tahap secara bertahap.');
    }

    public function edit(Request $request, CertificationApplication $application, DynamicFormService $forms, WorkflowService $workflow)
    {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403, 'Permohonan tidak dapat diubah pada tahap ini.');

        if ($application->status === 'revision_requested') {
            $application = $workflow->transition($application, 'client_revision', 'client_open_revision', 'Klien mulai memperbaiki item revisi.', $request->user()->id);
        }

        $application->load(['scheme.sections.fields.options', 'scheme.requiredDocuments', 'values', 'documents.currentVersion', 'revisions']);
        $application->setRelation('scheme', $forms->schemeForApplication($application));

        // Khusus skema SNI (LSPro): dropdown bertingkat Produk -> Kategori dari taksonomi.
        $productGroups = $application->scheme->review_template === 'sni'
            ? \App\Models\SniProductGroup::with('categories')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            : null;

        return view('client.applications.edit', [
            'application' => $application,
            'values' => $forms->values($application),
            'applicableDocuments' => $forms->applicableDocuments($application->scheme, $forms->values($application)),
            'completion' => $forms->completion($application),
            'productGroups' => $productGroups,
        ]);
    }

    public function update(Request $request, CertificationApplication $application, DynamicFormService $forms, ApplicationSubmissionService $service)
    {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);

        $input = (array) $request->input('fields', []);
        $application->loadMissing('scheme');
        $application->setRelation('scheme', $forms->schemeForApplication($application));
        validator(['fields' => $input], $forms->validationRules($application->scheme, $input, false))->validate();
        $service->saveValues($application, $input, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
        }

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    public function submit(Request $request, CertificationApplication $application, ApplicationSubmissionService $service)
    {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);
        $service->submit($application, $request->user()->id);

        return redirect()->route('client.applications.show', $application)
            ->with('success', 'Permohonan berhasil dikirim ke tim GIS.');
    }

    public function destroy(Request $request, CertificationApplication $application, AuditLogger $audit)
    {
        $this->own($request, $application);
        abort_unless($application->canBeDeletedByClient(), 403, 'Hanya draft yang belum dikirim yang dapat dihapus.');

        // Dicatat sebelum baris hilang; activity_logs tanpa FK jadi jejak tetap ada.
        $audit->log('application.draft_deleted_by_client', $application, $application->toArray());

        // Tabel anak ikut terhapus lewat cascade FK, tapi file fisik tidak.
        Storage::disk('private')->deleteDirectory('applications/' . $application->id);
        $application->delete();

        return redirect()->route('client.applications.index')
            ->with('success', 'Draft permohonan berhasil dihapus.');
    }

    public function show(Request $request, CertificationApplication $application, DynamicFormService $forms)
    {
        $this->own($request, $application);
        $application->load(['scheme', 'values', 'documents.currentVersion', 'revisions', 'statusHistory', 'invoice.payments', 'auditStages', 'findings.correctiveActions', 'certificateDrafts', 'certificateFinal', 'surveillanceSchedules']);

        return view('client.applications.show', [
            'application' => $application,
            'completion' => $forms->completion($application),
        ]);
    }

    private function own(Request $request, CertificationApplication $application): void
    {
        abort_unless($application->client_id === $request->user()->id, 403);
    }
}
