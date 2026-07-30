<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\CertificationApplication;
use App\Models\SchemeRequiredDocument;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function store(Request $request, CertificationApplication $application, FileStorageService $files, DynamicFormService $forms, AuditLogger $audit)
    {
        abort_unless($application->client_id === $request->user()->id && $application->canBeEditedByClient(), 403);

        $data = $request->validate([
            'document_code' => ['required', 'string'],
            'file' => ['required', 'file'],
        ]);

        $application->loadMissing('scheme');
        $snapshotDocument = $forms->schemeForApplication($application)->requiredDocuments->firstWhere('code', $data['document_code']);
        abort_unless($snapshotDocument, 404);

        $currentDocument = SchemeRequiredDocument::where('certification_scheme_id', $application->certification_scheme_id)
            ->where('code', $data['document_code'])
            ->first();
        $required = $snapshotDocument;

        $document = ApplicationDocument::firstOrCreate(
            ['application_id' => $application->id, 'document_code' => $required->code],
            ['scheme_required_document_id' => $currentDocument?->id, 'document_name' => $required->name]
        );

        $version = $files->storeDocumentVersion($document, $request->file('file'), $request->user()->id);
        $document->update(['review_status' => 'pending', 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
        $audit->log('application.document_uploaded', $document, [], ['version' => $version->version, 'original_name' => $version->original_name]);

        $message = 'Dokumen ' . $required->name . ' berhasil diunggah sebagai versi ' . $version->version . '.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'document_code' => $required->code,
                'original_name' => $version->original_name,
                'version' => $version->version,
                'review_status' => $document->review_status,
                'completion' => $forms->completion($application),
            ]);
        }

        return back()->with('success', $message);
    }

    public function download(Request $request, ApplicationDocument $document, FileStorageService $files, AuditLogger $audit)
    {
        $document->load(['application', 'currentVersion']);
        $application = $document->application;

        $allowed = $application->client_id === $request->user()->id
            || $request->user()->hasRole(['admin_application', 'superadmin']);

        if ($request->user()->hasRole('auditor')) {
            $allowed = $application->auditAssignments()
                ->where('auditor_id', $request->user()->id)
                ->where('status', 'assigned')
                ->exists();
        }

        abort_unless($allowed, 403);
        abort_unless($document->currentVersion, 404);
        $audit->log('file.application_document_downloaded', $document, [], ['application_id' => $application->id]);

        return $files->response($document->currentVersion->file_path, $document->currentVersion->original_name);
    }
}
