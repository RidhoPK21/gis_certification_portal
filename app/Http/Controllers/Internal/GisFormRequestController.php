<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\GisFormRequest;
use App\Services\GisFormService;
use Illuminate\Http\Request;

class GisFormRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'pending';

        return view('internal.gis-form-requests', [
            'status' => $status,
            'requests' => GisFormRequest::with(['application.scheme', 'application.client', 'requester', 'responder'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'pendingCount' => GisFormRequest::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, GisFormRequest $gisFormRequest, GisFormService $gisForms)
    {
        $data = $request->validate(['response_note' => ['nullable', 'string', 'max:1000']]);
        $gisForms->approve($gisFormRequest, $request->user()->id, $data['response_note'] ?? null);

        return back()->with('success', 'Template Formulir Wajib GIS dibagikan kepada klien.');
    }

    public function reject(Request $request, GisFormRequest $gisFormRequest, GisFormService $gisForms)
    {
        $data = $request->validate(['response_note' => ['required', 'string', 'max:1000']]);
        $gisForms->reject($gisFormRequest, $request->user()->id, $data['response_note']);

        return back()->with('success', 'Permintaan template ditolak dan alasannya dikirim ke klien.');
    }
}
