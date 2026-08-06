<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CertificationApplication;
use App\Services\GisFormService;
use Illuminate\Http\Request;

class GisFormRequestController extends Controller
{
    public function store(Request $request, CertificationApplication $application, GisFormService $gisForms)
    {
        abort_unless($application->client_id === $request->user()->id, 403);
        abort_unless($gisForms->schemeUsesGisForms($application->certification_scheme_id), 404);

        $data = $request->validate(['client_note' => ['nullable', 'string', 'max:1000']]);
        $gisForms->requestTemplates($application, $request->user()->id, $data['client_note'] ?? null);

        return back()->with('success', 'Permintaan template Formulir Wajib GIS terkirim. Anda akan diberi tahu setelah disetujui tim GIS.');
    }
}
