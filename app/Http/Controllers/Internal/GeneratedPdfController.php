<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\GeneratedPdf;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

class GeneratedPdfController extends Controller
{
    /**
     * Peran yang boleh mengunduh PDF hasil generate sistem.
     *
     * Pemeriksaan ini sengaja berada di dalam controller, bukan menumpang
     * middleware grup: routenya dipakai bersama Admin Permohonan dan Tim Teknis
     * sehingga tidak lagi berada di grup yang hanya berisi satu peran.
     */
    private const ALLOWED_ROLES = [
        'admin_application', 'admin_sustain',
        'technical', 'technical_sustain',
        'superadmin',
    ];

    public function download(Request $request, GeneratedPdf $pdf, FileStorageService $files, AuditLogger $audit)
    {
        abort_unless($request->user()->hasRole(self::ALLOWED_ROLES), 403);

        // Peran yang benar belum cukup: PDF ISPO hanya milik tim Sustain.
        $pdf->loadMissing('application');
        abort_unless($pdf->application?->isHandledBy($request->user()), 403);

        $prefix = $pdf->document_type === 'assignment_letter'
            ? 'Surat-Tugas'
            : 'Tinjauan-Permohonan';

        $audit->log('file.generated_pdf_downloaded', $pdf, [], [
            'application_id' => $pdf->application_id,
            'document_type' => $pdf->document_type,
        ]);

        return $files->response(
            $pdf->file_path,
            $prefix.'-'.$pdf->application_id.'-v'.$pdf->document_version.'.pdf',
            'inline'
        );
    }
}
