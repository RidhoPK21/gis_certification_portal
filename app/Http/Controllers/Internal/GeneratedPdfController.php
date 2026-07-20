<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\GeneratedPdf;
use App\Services\FileStorageService;

class GeneratedPdfController extends Controller
{
    public function download(GeneratedPdf $pdf, FileStorageService $files)
    {
        return $files->response(
            $pdf->file_path,
            'Tinjauan-Permohonan-'.$pdf->application_id.'-v'.$pdf->document_version.'.pdf',
            'inline'
        );
    }
}
