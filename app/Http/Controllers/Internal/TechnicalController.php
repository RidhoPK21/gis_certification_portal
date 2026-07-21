<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CertificateDraft;
use App\Models\CertificateFinal;
use App\Models\CertificateShareLink;
use App\Models\CertificationApplication;
use App\Services\AuditLogger;
use App\Services\CertificateLinkService;
use App\Services\FileStorageService;
use App\Services\PortalNotificationService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TechnicalController extends Controller
{
    private const TECHNICAL_STATUSES = ['certificate_review', 'final_certificate', 'completed', 'surveillance'];

    public function index()
    {
        return view('internal.technical.index', [
            'applications' => CertificationApplication::whereIn('status', self::TECHNICAL_STATUSES)
                ->with(['scheme', 'client', 'certificateDrafts', 'certificateFinal'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(CertificationApplication $application)
    {
        abort_unless(in_array($application->status, self::TECHNICAL_STATUSES, true), 422, 'Order belum berada pada tahap Tim Teknis.');
        $application->load(['scheme', 'client', 'certificateDrafts', 'certificateFinal', 'generatedPdfs']);
        $links = CertificateShareLink::where('application_id', $application->id)->latest()->get();

        return view('internal.technical.show', compact('application', 'links'));
    }

    public function uploadDraft(Request $request, CertificationApplication $application, FileStorageService $files, AuditLogger $audit)
    {
        abort_unless($application->status === 'certificate_review', 422, 'Draft hanya dapat diunggah pada tahap review sertifikat.');
        $data = $request->validate(['draft' => ['required', 'file', 'mimes:pdf'], 'notes' => ['nullable', 'string']]);
        $files->validate($request->file('draft'));
        $version = ((int) $application->certificateDrafts()->max('version')) + 1;
        $name = $application->id.'_certificate_draft_v'.$version.'_'.Str::random(8).'.pdf';
        $path = $request->file('draft')->storeAs('applications/'.$application->id.'/certificates', $name, 'private');
        $draft = CertificateDraft::create([
            'application_id' => $application->id, 'version' => $version,
            'original_name' => $request->file('draft')->getClientOriginalName(), 'file_path' => $path,
            'checksum_sha256' => hash_file('sha256', $request->file('draft')->getRealPath()),
            'status' => 'ready_for_preview', 'notes' => $data['notes'] ?? null, 'uploaded_by' => $request->user()->id,
        ]);
        $audit->log('certificate.draft_uploaded', $draft);

        return back()->with('success', 'Draft sertifikat versi '.$version.' berhasil diunggah.');
    }

    public function createDraftLink(Request $request, CertificateDraft $draft, CertificateLinkService $links, PortalNotificationService $notifications, AuditLogger $audit)
    {
        $data = $request->validate(['expires_at' => ['nullable', 'date', 'after:now']]);
        $draft->load('application');
        $result = $links->create($draft->application, 'draft', $draft->id, $request->user()->id, isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null);
        $url = route('certificate.draft.preview', $result['token']);
        $notifications->send($draft->application->client_id, 'draft_available', 'Draft sertifikat tersedia', 'Draft sertifikat untuk '.$draft->application->order_number.' siap ditinjau.', $url);
        $audit->log('certificate.draft_link_created', $result['link'], [], ['expires_at' => $result['link']->expires_at]);

        return back()->with('generated_link', ['url' => $url, 'password' => null])->with('success', 'Link preview draft berhasil dibuat. Salin link sebelum meninggalkan halaman.');
    }

    public function uploadFinal(Request $request, CertificationApplication $application, FileStorageService $files, WorkflowService $workflow, AuditLogger $audit)
    {
        abort_unless(in_array($application->status, ['certificate_review', 'final_certificate'], true), 422, 'Sertifikat final hanya dapat diunggah setelah tahap audit selesai.');
        $data = $request->validate([
            'certificate' => ['required', 'file', 'mimes:pdf'],
            'certificate_number' => ['required', 'string', 'max:100', 'unique:certificate_finals,certificate_number'],
            'issued_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issued_date'],
        ]);
        $files->validate($request->file('certificate'));
        $name = $application->id.'_certificate_final_'.Str::random(8).'.pdf';
        $path = $request->file('certificate')->storeAs('applications/'.$application->id.'/certificates', $name, 'private');
        $final = CertificateFinal::create([
            'application_id' => $application->id, 'certificate_number' => $data['certificate_number'],
            'original_name' => $request->file('certificate')->getClientOriginalName(), 'file_path' => $path,
            'checksum_sha256' => hash_file('sha256', $request->file('certificate')->getRealPath()),
            'issued_date' => $data['issued_date'], 'expiry_date' => $data['expiry_date'] ?? null,
            'status' => 'released', 'uploaded_by' => $request->user()->id,
        ]);
        if ($application->status === 'certificate_review') {
            $workflow->transition($application, 'final_certificate', 'final_certificate_uploaded', 'Sertifikat final diunggah.', $request->user()->id, new \DateTime($data['issued_date']));
        }
        // Catatan: pembuatan rencana surveillance otomatis ditambahkan pada Fase 8.
        $audit->log('certificate.final_uploaded', $final);

        return back()->with('success', 'Sertifikat final berhasil diunggah. Buat link aman untuk klien.');
    }

    public function createFinalLink(Request $request, CertificateFinal $final, CertificateLinkService $links, PortalNotificationService $notifications, AuditLogger $audit)
    {
        $data = $request->validate(['expires_at' => ['nullable', 'date', 'after:now']]);
        $final->load('application');
        $result = $links->create($final->application, 'final', $final->id, $request->user()->id, isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null);
        $url = route('certificate.final.access', $result['token']);
        $notifications->send($final->application->client_id, 'final_available', 'Sertifikat final tersedia', 'Sertifikat final untuk '.$final->application->order_number.' telah tersedia. Gunakan link dan password yang diberikan oleh GIS.', $url);
        $audit->log('certificate.final_link_created', $result['link'], [], ['expires_at' => $result['link']->expires_at]);

        return back()->with('generated_link', ['url' => $url, 'password' => $result['password']])->with('success', 'Link dan password final berhasil dibuat. Password hanya ditampilkan sekali.');
    }

    public function complete(Request $request, CertificationApplication $application, WorkflowService $workflow, AuditLogger $audit)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:3000'], 'action_date' => ['required', 'date']]);
        abort_unless($application->status === 'final_certificate', 422, 'Order hanya dapat diselesaikan setelah sertifikat final diunggah.');
        abort_unless($application->certificateFinal()->exists(), 422, 'Sertifikat final belum tersedia.');
        $workflow->transition($application, 'completed', 'certification_completed', $data['notes'], $request->user()->id, new \DateTime($data['action_date']));
        // Catatan: aktivasi status surveillance ditambahkan pada Fase 8.
        $audit->log('application.completed', $application);

        return back()->with('success', 'Proses sertifikasi ditutup.');
    }

    public function revoke(Request $request, CertificateShareLink $link, AuditLogger $audit)
    {
        $link->update(['is_active' => false, 'revoked_at' => now(), 'revoked_by' => $request->user()->id]);
        $audit->log('certificate.link_revoked', $link);

        return back()->with('success', 'Link sertifikat telah dinonaktifkan.');
    }
}
