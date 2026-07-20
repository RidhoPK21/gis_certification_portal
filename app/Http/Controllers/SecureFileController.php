<?php

namespace App\Http\Controllers;

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

    // Catatan: auditReport() dan correctiveAction() ditambahkan pada Fase 6.
}
