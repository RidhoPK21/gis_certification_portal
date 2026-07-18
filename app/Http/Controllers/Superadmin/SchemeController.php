<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\CertificationScheme;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(): View
    {
        return view('superadmin.schemes', [
            'schemes' => CertificationScheme::withCount(['sections', 'requiredDocuments'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function edit(CertificationScheme $scheme): View
    {
        $scheme->load(['sections.fields.options', 'requiredDocuments']);

        return view('superadmin.scheme-edit', compact('scheme'));
    }

    public function update(Request $request, CertificationScheme $scheme, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'short_name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'standard' => ['nullable', 'string'],
            'order_prefix' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $scheme->only(array_keys($data));
        $data['is_active'] = $request->boolean('is_active');
        $scheme->update($data);
        $audit->log('scheme.updated', $scheme, $old, $data);

        return back()->with('success', 'Konfigurasi skema diperbarui.');
    }
}
