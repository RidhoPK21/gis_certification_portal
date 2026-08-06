<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CertificateDraft;
use App\Models\CertificateFinal;
use App\Models\CertificateShareLink;
use App\Models\CertificationApplication;
use App\Models\SurveillanceSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CertificateLinkService;
use App\Services\DynamicFormService;
use App\Services\FileStorageService;
use App\Services\PortalNotificationService;
use App\Services\QrCodeService;
use App\Services\ReviewService;
use App\Services\SurveillancePlannerService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechnicalController extends Controller
{
    private const TECHNICAL_STATUSES = ['certificate_review', 'final_certificate', 'completed', 'surveillance'];

    public function index(Request $request)
    {
        $query = CertificationApplication::whereIn('status', self::TECHNICAL_STATUSES)
            ->with(['scheme', 'client', 'certificateDrafts', 'certificateFinal']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('internal.technical.index', [
            'applications' => $query->latest()->paginate(20)->withQueryString(),
            'schemes' => \App\Models\CertificationScheme::orderBy('sort_order')->get(),
        ]);
    }

    public function show(CertificationApplication $application)
    {
        abort_unless(in_array($application->status, self::TECHNICAL_STATUSES, true), 422, 'Order belum berada pada tahap Tim Teknis.');
        $application->load(['scheme', 'client', 'certificateDrafts', 'certificateFinal', 'generatedPdfs', 'surveillanceSchedules']);
        $links = $application->certificateShareLinks()->latest()->get();

        return view('internal.technical.show', compact('application', 'links'));
    }

    public function reviewIndex(Request $request)
    {
        $query = CertificationApplication::where('status', 'technical_review')
            ->with(['scheme', 'client']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('internal.technical.reviews', [
            'applications' => $query->latest()->paginate(20)->withQueryString(),
            'schemes' => \App\Models\CertificationScheme::orderBy('sort_order')->get(),
        ]);
    }

    public function reviewShow(CertificationApplication $application, DynamicFormService $forms, ReviewService $reviews)
    {
        abort_unless($application->status === 'technical_review', 422, 'Permohonan belum berada pada tahap tinjauan teknis.');
        $application->load(['scheme.requiredDocuments', 'client', 'values', 'documents.currentVersion', 'reviews.items', 'auditAssignments.auditor']);
        $application->setRelation('scheme', $forms->schemeForApplication($application));
        $review = $application->reviews->where('review_type', 'technical')->sortByDesc('round')->first();

        return view('internal.technical.review-show', [
            'application' => $application,
            'technicalFields' => config('review.technical_fields'),
            // Baris tabel mengikuti formulir tinjauan, bukan seluruh checklist klien.
            'technicalDocuments' => $reviews->formRows($application, 'technical'),
            'review' => $review,
            'panelistCandidates' => User::where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('code', config('review.panelist_roles')))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function saveTechnicalReview(Request $request, CertificationApplication $application, ReviewService $reviews, AuditLogger $audit)
    {
        abort_unless($application->status === 'technical_review', 422, 'Permohonan belum berada pada tahap tinjauan teknis.');
        $data = $request->validate([
            // signed_name diabaikan bila dikirim: nilainya diambil dari akun peninjau.
            'notes' => ['nullable', 'string'], 'action_date' => ['required', 'date'], 'signed_name' => ['nullable', 'string', 'max:150'],
            'aspects' => ['nullable', 'array'],
            'aspects.*' => ['nullable', 'string', 'max:3000'],
            'scope_conformity' => ['nullable', Rule::in(array_keys(config('review.technical_conclusion.scope_conformity.options')))],
            'audit_capability_choice' => ['nullable', Rule::in(array_keys(config('review.technical_conclusion.audit_capability_choice.options')))],
            'panelist_ids' => ['nullable', 'array'],
            'panelist_ids.*' => ['integer', 'exists:users,id'],
            'auditor_competence_codes' => ['nullable', 'array'],
            'auditor_competence_codes.*' => [Rule::in(array_keys(config('review.environmental_competences')))],
            'items' => ['nullable', 'array'], 'items.*.type' => ['required_with:items', 'string'], 'items.*.code' => ['required_with:items', 'string'],
            'items.*.label' => ['required_with:items', 'string'], 'items.*.presence' => ['nullable', 'string'],
            'items.*.status' => ['required_with:items', Rule::in(ReviewService::STATUSES)],
            'items.*.remark_option' => ['nullable', Rule::in(ReviewService::REMARK_OPTIONS)],
            'items.*.remark_date' => ['nullable', 'date', 'required_if:items.*.remark_option,tgl_berlaku'],
            'items.*.notes' => ['nullable', 'string'],
        ], [
            'items.*.remark_date.required_if' => 'Tanggal berlaku wajib diisi bila keterangan memilih "Tgl Berlaku".',
        ]);

        /*
         * Panelis harus benar-benar akun berperan panelis; tanpa pagar ini
         * sembarang id pengguna bisa dikirim lewat request.
         */
        if (! empty($data['panelist_ids'])) {
            $valid = User::whereIn('id', $data['panelist_ids'])
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('code', config('review.panelist_roles')))
                ->pluck('id')
                ->all();

            abort_if(count($valid) !== count(array_unique($data['panelist_ids'])), 422, 'Panelis yang dipilih tidak valid.');
        }
        $review = $reviews->save($application, 'technical', $data, $request->user()->id, 'technical');
        $reviews->storeTechnicalAspects($application, $data['aspects'] ?? [], $request->user()->id);
        $audit->log('application.review_saved', $review, [], ['application_id' => $application->id, 'type' => 'technical']);

        return back()->with('success', 'Tinjauan teknis berhasil disimpan. Klik "Selesai & Kirim ke Admin" bila sudah final.');
    }

    public function completeTechnicalReview(Request $request, CertificationApplication $application, WorkflowService $workflow, PortalNotificationService $notifications, AuditLogger $audit)
    {
        abort_unless($application->status === 'technical_review', 422, 'Permohonan belum berada pada tahap tinjauan teknis.');
        $review = $application->reviews()->where('review_type', 'technical')->latest()->first();
        abort_unless($review, 422, 'Isi dan simpan tinjauan teknis terlebih dahulu.');

        $review->update(['completed_at' => now(), 'reviewed_by' => $request->user()->id]);
        $workflow->transition($application, 'admin_review', 'technical_review_done', 'Tinjauan teknis selesai, dikembalikan ke Admin untuk keputusan.', $request->user()->id);
        $notifications->sendToRole('admin_application', 'technical_review_completed', 'Tinjauan Teknis Selesai', 'Tinjauan teknis untuk '.$application->order_number.' telah selesai dan siap disetujui.', route('internal.applications.show', $application));
        $audit->log('application.technical_review_completed', $application, [], ['application_id' => $application->id]);

        return redirect()->route('technical.reviews.index')->with('success', 'Tinjauan teknis dikirim ke Admin untuk keputusan akhir.');
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

        return $this->savedResponse($request, 'Draft sertifikat versi '.$version.' berhasil diunggah.');
    }

    public function createDraftLink(Request $request, CertificateDraft $draft, CertificateLinkService $links, PortalNotificationService $notifications, AuditLogger $audit)
    {
        $data = $request->validate(['expires_at' => ['nullable', 'date', 'after:now']]);
        $draft->load('application');
        $result = $links->create($draft->application, 'draft', $draft->id, $request->user()->id, isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null);
        $url = route('certificate.draft.preview', $result['token']);
        $notifications->send($draft->application->client_id, 'draft_available', 'Draft sertifikat tersedia', 'Draft sertifikat untuk '.$draft->application->order_number.' siap ditinjau.', $url, ['application_id' => $draft->application->id]);
        $audit->log('certificate.draft_link_created', $result['link'], [], ['expires_at' => $result['link']->expires_at]);

        // withFragment: browser melompat ke banner link, mengalahkan pemulihan
        // posisi scroll yang membuat banner tidak pernah terlihat staf.
        return back()->withFragment('generated-link')->with('generated_link', ['url' => $url, 'password' => null])->with('success', 'Link preview draft berhasil dibuat. Salin link sebelum meninggalkan halaman.');
    }

    public function uploadFinal(Request $request, CertificationApplication $application, FileStorageService $files, WorkflowService $workflow, SurveillancePlannerService $planner, AuditLogger $audit)
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
        $planner->generate($final, $request->user()->id);
        $audit->log('certificate.final_uploaded', $final, [], ['surveillance_plans_generated' => true]);

        return $this->savedResponse($request, 'Sertifikat final berhasil diunggah. Buat link aman untuk klien.');
    }

    public function createFinalLink(Request $request, CertificateFinal $final, CertificateLinkService $links, PortalNotificationService $notifications, AuditLogger $audit, QrCodeService $qr)
    {
        $data = $request->validate(['expires_at' => ['nullable', 'date', 'after:now']]);
        $final->load('application');
        $result = $links->create($final->application, 'final', $final->id, $request->user()->id, isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null);
        $url = route('certificate.final.access', $result['token']);
        $notifications->send($final->application->client_id, 'final_available', 'Sertifikat final tersedia', 'Sertifikat final untuk '.$final->application->order_number.' telah tersedia. Gunakan link dan password yang diberikan oleh GIS.', $url, ['application_id' => $final->application->id]);
        $audit->log('certificate.final_link_created', $result['link'], [], ['expires_at' => $result['link']->expires_at]);

        /*
         * QR sengaja mengarah ke halaman verifikasi publik, BUKAN ke link
         * unduhan: QR pada sertifikat dipakai pihak ketiga untuk memastikan
         * keaslian, dan tidak boleh membawa token akses berkas.
         */
        $verifyUrl = $qr->verificationUrl($final->certificate_number);

        return back()->withFragment('generated-link')->with('generated_link', [
            'url' => $url,
            'password' => $result['password'],
            'verify_url' => $verifyUrl,
            'verify_qr' => $qr->svg($verifyUrl),
            'certificate_number' => $final->certificate_number,
        ])->with('success', 'Link dan password final berhasil dibuat. Password hanya ditampilkan sekali.');
    }

    public function complete(Request $request, CertificationApplication $application, WorkflowService $workflow, AuditLogger $audit)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:3000'], 'action_date' => ['required', 'date']]);
        abort_unless($application->status === 'final_certificate', 422, 'Order hanya dapat diselesaikan setelah sertifikat final diunggah.');
        abort_unless($application->certificateFinal()->exists(), 422, 'Sertifikat final belum tersedia.');
        $workflow->transition($application, 'completed', 'certification_completed', $data['notes'], $request->user()->id, new \DateTime($data['action_date']));
        if ($application->surveillanceSchedules()->exists()) {
            $workflow->transition($application->refresh(), 'surveillance', 'surveillance_activated', 'Rencana surveillance otomatis diaktifkan.', $request->user()->id, new \DateTime($data['action_date']));
        }
        $audit->log('application.completed', $application, [], ['surveillance_activated' => $application->surveillanceSchedules()->exists()]);

        return back()->with('success', 'Proses sertifikasi ditutup dan rencana surveillance diaktifkan.');
    }

    public function updateSurveillance(Request $request, SurveillanceSchedule $schedule, AuditLogger $audit)
    {
        $data = $request->validate([
            'scheduled_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $old = $schedule->toArray();
        $schedule->update($data + ['updated_by' => $request->user()->id]);
        $audit->log('surveillance.updated', $schedule, $old, $schedule->fresh()->toArray());

        return back()->with('success', 'Jadwal surveillance berhasil diperbarui.');
    }

    public function revoke(Request $request, CertificateShareLink $link, AuditLogger $audit)
    {
        $link->update(['is_active' => false, 'revoked_at' => now(), 'revoked_by' => $request->user()->id]);
        $audit->log('certificate.link_revoked', $link);

        return back()->with('success', 'Link sertifikat telah dinonaktifkan.');
    }
}
