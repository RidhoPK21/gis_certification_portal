<?php

namespace App\Http\Controllers;

use App\Models\AuditStageFile;
use App\Models\CertificationApplication;
use App\Models\CorrectiveActionFile;
use App\Models\Invoice;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

class SecureFileController extends Controller
{
    public function invoice(Request $request, Invoice $invoice, FileStorageService $files, AuditLogger $audit)
    {
        $invoice->load('application');
        $allowed = $invoice->application->client_id === $request->user()->id
            || $request->user()->hasRole(['finance', 'admin_application', 'superadmin']);
        abort_unless($allowed && filled($invoice->file_path), 403);
        $audit->log('file.invoice_downloaded', $invoice);

        return $files->response($invoice->file_path, 'invoice-'.$invoice->invoice_number.'.'.pathinfo($invoice->file_path, PATHINFO_EXTENSION));
    }

    public function auditReport(Request $request, AuditStageFile $file, FileStorageService $files, AuditLogger $audit)
    {
        $file->load('auditStage.application');
        $application = $file->auditStage->application;
        $allowed = $request->user()->hasRole(['admin_application', 'superadmin']);
        if ($request->user()->hasRole('auditor')) {
            $allowed = $application->auditAssignments()->where('auditor_id', $request->user()->id)->where('status', 'assigned')->exists();
        }
        abort_unless($allowed, 403);
        $audit->log('file.audit_report_downloaded', $file, [], ['application_id' => $application->id]);

        return $files->response($file->file_path, $file->original_name);
    }

    public function correctiveAction(Request $request, CorrectiveActionFile $file, FileStorageService $files, AuditLogger $audit)
    {
        $file->load('correctiveAction.finding.application');
        $application = $file->correctiveAction->finding->application;
        $allowed = $application->client_id === $request->user()->id
            || $request->user()->hasRole(['admin_application', 'superadmin']);
        if ($request->user()->hasRole('auditor')) {
            $allowed = $application->auditAssignments()->where('auditor_id', $request->user()->id)->where('status', 'assigned')->exists();
        }
        abort_unless($allowed, 403);
        $audit->log('file.corrective_action_downloaded', $file, [], ['application_id' => $application->id]);

        return $files->response($file->file_path, $file->original_name);
    }

    public function fieldFile(Request $request, CertificationApplication $application, string $code, FileStorageService $files, AuditLogger $audit)
    {
        $allowed = $application->client_id === $request->user()->id
            || $request->user()->hasRole(['admin_application', 'superadmin', 'technical']);
        if ($request->user()->hasRole('auditor')) {
            $allowed = $application->auditAssignments()->where('auditor_id', $request->user()->id)->where('status', 'assigned')->exists();
        }
        abort_unless($allowed, 403);

        $value = $application->values()->where('field_code', $code)->first();
        abort_unless($value && $value->value_json, 404);

        $data = $value->value_json;
        $path = $data['path'] ?? null;
        $name = $data['original_name'] ?? 'file';
        abort_unless($path, 404);

        $audit->log('file.application_field_file_downloaded', $application, [], ['field_code' => $code, 'original_name' => $name]);

        return $files->response($path, $name);
    }
}
