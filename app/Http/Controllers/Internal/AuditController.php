<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditStage;
use App\Models\AuditStageFile;
use App\Models\CertificationApplication;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionReview;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use App\Services\PortalNotificationService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificationApplication::whereIn('status', [
            'payment_completed', 'stage_1_audit', 'stage_2_audit', 'qms_audit',
            'corrective_action', 'corrective_revision',
        ])->with(['scheme', 'client', 'auditStages', 'auditAssignments.auditor'])->latest();

        if (! $request->user()->hasRole('superadmin')) {
            $query->whereHas('auditAssignments', fn ($assignment) => $assignment
                ->where('auditor_id', $request->user()->id)
                ->where('status', 'assigned'));
        }

        return view('internal.audit.index', ['applications' => $query->paginate(20)]);
    }

    public function show(Request $request, CertificationApplication $application)
    {
        $this->ensureAssigned($request, $application);
        $application->load([
            'scheme', 'client', 'auditAssignments.auditor', 'auditStages.files',
            'findings.correctiveActions.files', 'findings.correctiveActions.reviews',
        ]);

        return view('internal.audit.show', compact('application'));
    }

    public function saveStage(Request $request, CertificationApplication $application, WorkflowService $workflow, FileStorageService $files, AuditLogger $audit)
    {
        $data = $request->validate([
            'stage_code' => ['required', Rule::in(['stage_1', 'stage_2', 'qms'])],
            'status' => ['required', Rule::in(['uploaded', 'approved', 'revision'])],
            'audit_date' => ['required', 'date'],
            'mandays' => ['nullable', 'numeric', 'min:0'],
            'auditor_team' => ['required', 'string'],
            'summary' => ['nullable', 'string'],
            'report.*' => ['nullable', 'file'],
        ]);
        $this->ensureAssigned($request, $application, $data['stage_code']);
        unset($data['report']);

        $stage = AuditStage::updateOrCreate(
            ['application_id' => $application->id, 'stage_code' => $data['stage_code']],
            $data + ['action_date' => $data['audit_date'], 'updated_by' => $request->user()->id],
        );

        foreach ($request->file('report', []) as $file) {
            $files->validate($file);
            $name = $application->id.'_'.$data['stage_code'].'_'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('applications/'.$application->id.'/audit', $name, 'private');
            AuditStageFile::create([
                'audit_stage_id' => $stage->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $target = match ($data['stage_code']) {
            'stage_1' => 'stage_1_audit',
            'stage_2' => 'stage_2_audit',
            default => 'qms_audit',
        };
        if ($application->status === 'payment_completed') {
            $workflow->transition($application, $target, 'audit_stage_opened', 'Tahap '.$data['stage_code'].' dimulai.', $request->user()->id);
        } elseif ($application->status === 'stage_1_audit' && $target === 'stage_2_audit') {
            $workflow->transition($application, $target, 'audit_stage_advanced', 'Lanjut Stage 2.', $request->user()->id);
        } elseif (in_array($application->status, ['stage_1_audit', 'stage_2_audit'], true) && $target === 'qms_audit') {
            $workflow->transition($application, $target, 'audit_stage_advanced', 'Lanjut audit lapangan/QMS.', $request->user()->id);
        }

        $audit->log('audit.stage_saved', $stage);

        return $this->savedResponse($request, 'Tahap audit berhasil disimpan.');
    }

    public function skipStage(Request $request, CertificationApplication $application, WorkflowService $workflow, AuditLogger $audit)
    {
        $data = $request->validate([
            'stage_code' => ['required', Rule::in(['stage_1', 'stage_2'])],
            'reason' => ['required', 'string'],
            'action_date' => ['required', 'date'],
        ]);
        $this->ensureAssigned($request, $application, $data['stage_code']);

        $step = $application->workflowSteps()
            ->whereHas('workflowStep', fn ($query) => $query->where('code', $data['stage_code'])->where('is_skippable', true))
            ->first();
        abort_unless($step, 422, 'Tahap ini tidak dikonfigurasi sebagai skippable untuk skema tersebut.');

        $stage = AuditStage::updateOrCreate(
            ['application_id' => $application->id, 'stage_code' => $data['stage_code']],
            ['status' => 'skipped', 'skip_reason' => $data['reason'], 'action_date' => $data['action_date'], 'updated_by' => $request->user()->id],
        );
        $step->update([
            'status' => 'skipped', 'skip_reason' => $data['reason'],
            'skipped_by' => $request->user()->id, 'completed_at' => now(),
        ]);

        $target = $data['stage_code'] === 'stage_1' ? 'stage_2_audit' : 'qms_audit';
        if (in_array($application->status, ['payment_completed', 'stage_1_audit', 'stage_2_audit'], true)) {
            $workflow->transition($application, $target, 'audit_stage_skipped', $data['reason'], $request->user()->id, new \DateTime($data['action_date']));
        }
        $audit->log('audit.stage_skipped', $stage, [], ['reason' => $data['reason']]);

        return back()->with('success', 'Tahap audit dilewati dengan alasan yang tercatat.');
    }

    public function completeAudit(Request $request, CertificationApplication $application, WorkflowService $workflow, AuditLogger $audit)
    {
        $this->ensureAssigned($request, $application, 'qms');
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:3000'],
            'action_date' => ['required', 'date'],
        ]);
        abort_unless($application->status === 'qms_audit', 422, 'Audit hanya dapat diselesaikan dari tahap QMS/audit lapangan.');
        abort_if($application->findings()->where('status', '!=', 'closed')->exists(), 422, 'Masih ada temuan terbuka. Selesaikan tindakan koreksi terlebih dahulu.');
        $workflow->transition($application, 'certificate_review', 'audit_completed_without_open_findings', $data['notes'], $request->user()->id, new \DateTime($data['action_date']));
        $audit->log('audit.completed', $application, [], ['notes' => $data['notes']]);

        return back()->with('success', 'Audit selesai dan order diteruskan ke Tim Teknis.');
    }

    public function createFinding(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        $this->ensureAssigned($request, $application, 'qms');
        $data = $request->validate([
            'finding_number' => ['required', 'string', 'max:60'],
            'finding_type' => ['required', Rule::in(['major', 'minor', 'observation'])],
            'clause_reference' => ['nullable', 'string'],
            'description' => ['required', 'string'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $finding = $application->findings()->create($data + ['status' => 'open', 'created_by' => $request->user()->id]);
        if ($application->status === 'qms_audit') {
            $workflow->transition($application, 'corrective_action', 'finding_issued', 'Temuan '.$data['finding_number'].' diterbitkan.', $request->user()->id);
        }
        $notifications->send(
            $application->client_id,
            'finding_issued',
            'Tindakan koreksi diperlukan',
            'Temuan '.$data['finding_number'].' memerlukan tindakan koreksi sebelum '.$data['due_date'].'.',
            route('client.corrective-actions.index'),
        );
        $audit->log('audit.finding_created', $finding);

        return back()->with('success', 'Temuan berhasil diterbitkan.');
    }

    public function reviewCorrectiveAction(Request $request, CorrectiveAction $correctiveAction, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        $correctiveAction->load('finding.application');
        $application = $correctiveAction->finding->application;
        $this->ensureAssigned($request, $application, 'corrective_action');

        $data = $request->validate([
            'status' => ['required', Rule::in(['sufficient', 'insufficient', 'revision', 'accepted'])],
            'notes' => ['required', 'string'],
            'action_date' => ['required', 'date'],
        ]);
        $review = CorrectiveActionReview::create([
            'corrective_action_id' => $correctiveAction->id,
            'status' => $data['status'],
            'notes' => $data['notes'],
            'action_date' => $data['action_date'],
            'reviewed_by' => $request->user()->id,
        ]);
        $finding = $correctiveAction->finding;

        if (in_array($data['status'], ['sufficient', 'accepted'], true)) {
            $correctiveAction->update(['status' => 'accepted']);
            $finding->update(['status' => 'closed']);
            if ($application->findings()->where('status', '!=', 'closed')->doesntExist() && $application->status === 'corrective_action') {
                $workflow->transition($application, 'certificate_review', 'corrective_actions_closed', 'Seluruh tindakan koreksi diterima.', $request->user()->id);
            }
        } else {
            $correctiveAction->update(['status' => 'revision']);
            $finding->update(['status' => 'revision']);
            if ($application->status === 'corrective_action') {
                $workflow->transition($application, 'corrective_revision', 'corrective_action_revision', $data['notes'], $request->user()->id);
            }
            $notifications->send(
                $application->client_id,
                'ca_revision',
                'Revisi tindakan koreksi',
                'Tindakan koreksi untuk '.$finding->finding_number.' perlu diperbaiki.',
                route('client.corrective-actions.index'),
            );
        }
        $audit->log('corrective_action.reviewed', $review);

        return back()->with('success', 'Review tindakan koreksi berhasil disimpan.');
    }

    private function ensureAssigned(Request $request, CertificationApplication $application, ?string $stageCode = null): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        $query = $application->auditAssignments()
            ->where('auditor_id', $request->user()->id)
            ->where('status', 'assigned');
        if ($stageCode !== null) {
            $query->whereIn('stage_code', ['all', $stageCode]);
        }
        abort_unless($query->exists(), 403, 'Order ini belum ditugaskan kepada akun auditor Anda.');
    }
}
