<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CorrectiveActionFile;
use App\Models\Finding;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CorrectiveActionController extends Controller
{
    public function index(Request $request)
    {
        $findings = Finding::whereHas('application', fn ($q) => $q->where('client_id', $request->user()->id))
            ->with(['correctiveActions.files', 'correctiveActions.reviews', 'application.scheme'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('client.corrective-actions.index', compact('findings'));
    }

    public function store(Request $request, Finding $finding, FileStorageService $files, WorkflowService $workflow, AuditLogger $audit)
    {
        abort_unless($finding->application()->where('client_id', $request->user()->id)->exists(), 403);
        $data = $request->validate([
            'root_cause' => ['required', 'string'],
            'correction' => ['required', 'string'],
            'corrective_action' => ['required', 'string'],
            'implementation_date' => ['nullable', 'date'],
            'evidence.*' => ['nullable', 'file'],
        ]);
        unset($data['evidence']);
        $revision = ((int) $finding->correctiveActions()->max('revision')) + 1;
        $ca = $finding->correctiveActions()->create($data + ['revision' => $revision, 'status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
        foreach ($request->file('evidence', []) as $file) {
            $files->validate($file);
            $name = $finding->application_id.'_ca_'.$ca->id.'_'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('applications/'.$finding->application_id.'/corrective-actions', $name, 'private');
            CorrectiveActionFile::create([
                'corrective_action_id' => $ca->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $request->user()->id,
            ]);
        }
        $finding->update(['status' => 'ca_submitted']);
        $application = $finding->application;
        if ($application->status === 'corrective_revision') {
            $workflow->transition($application, 'corrective_action', 'client_corrective_action_resubmitted', 'Klien mengirim revisi tindakan koreksi.', $request->user()->id);
        }
        $audit->log('corrective_action.submitted', $ca);

        return $this->savedResponse($request, 'Tindakan koreksi berhasil dikirim kepada auditor.');
    }
}
