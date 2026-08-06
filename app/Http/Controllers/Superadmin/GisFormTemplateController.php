<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\CertificationScheme;
use App\Models\GisFormTemplate;
use App\Models\SchemeRequiredDocument;
use App\Services\AuditLogger;
use App\Services\GisFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GisFormTemplateController extends Controller
{
    public function index(Request $request, GisFormService $gisForms)
    {
        $schemes = CertificationScheme::orderBy('sort_order')->get();
        $scheme = $schemes->firstWhere('slug', $request->query('scheme')) ?? $schemes->first();

        abort_unless($scheme, 404);

        return view('superadmin.gis-forms', [
            'schemes' => $schemes,
            'scheme' => $scheme,
            'templates' => GisFormTemplate::where('certification_scheme_id', $scheme->id)
                ->with('requiredDocument')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            // Hanya item bergrup gis_form yang masuk akal dikaitkan ke template.
            'documents' => SchemeRequiredDocument::where('certification_scheme_id', $scheme->id)
                ->where('document_group', GisFormService::GROUP)
                ->orderBy('sort_order')
                ->get(),
            'usesGisForms' => $gisForms->schemeUsesGisForms($scheme->id),
        ]);
    }

    public function store(Request $request, CertificationScheme $scheme, GisFormService $gisForms, AuditLogger $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheme_required_document_id' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'file' => ['required', 'file'],
        ]);

        /*
         * Item checklist yang dikaitkan wajib milik skema ini, agar template
         * satu skema tidak bisa ditempelkan ke checklist skema lain.
         */
        if (filled($data['scheme_required_document_id'] ?? null)) {
            $owned = SchemeRequiredDocument::where('id', $data['scheme_required_document_id'])
                ->where('certification_scheme_id', $scheme->id)
                ->exists();
            abort_unless($owned, 422);
        }

        $template = $gisForms->storeTemplate($scheme->id, $data, $request->file('file'), $request->user()->id);
        $audit->log('gis_form_template.saved', $template, [], ['scheme' => $scheme->code, 'version' => $template->version]);

        return redirect()
            ->route('superadmin.gis-forms.index', ['scheme' => $scheme->slug])
            ->with('success', 'Template ' . $template->code . ' tersimpan sebagai versi ' . $template->version . '.');
    }

    public function toggle(Request $request, GisFormTemplate $template, AuditLogger $audit)
    {
        $template->update(['is_active' => ! $template->is_active]);
        $audit->log('gis_form_template.toggled', $template, [], ['is_active' => $template->is_active]);

        return back()->with('success', 'Template ' . $template->code . ' ' . ($template->is_active ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    public function destroy(Request $request, GisFormTemplate $template, AuditLogger $audit)
    {
        $audit->log('gis_form_template.deleted', $template, $template->toArray());
        Storage::disk('private')->delete($template->file_path);
        $code = $template->code;
        $template->delete();

        return back()->with('success', 'Template ' . $code . ' dihapus.');
    }
}
