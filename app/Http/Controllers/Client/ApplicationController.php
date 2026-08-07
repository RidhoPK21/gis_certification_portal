<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\PortalNotification;
use App\Services\ApplicationSubmissionService;
use App\Services\AuditLogger;
use App\Services\DynamicFormService;
use App\Services\GisFormService;
use App\Services\IspoApplicationPdfService;
use App\Services\QrCodeService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = CertificationApplication::where('client_id', $request->user()->id)
            ->with('scheme');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('client.applications.index', [
            'applications' => $query->latest()->paginate(12)->withQueryString(),
            'schemes' => CertificationScheme::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function schemes()
    {
        return view('client.applications.schemes', [
            'schemes' => CertificationScheme::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(CertificationScheme $scheme)
    {
        abort_unless($scheme->is_active, 404);

        return view('client.applications.create', ['scheme' => $scheme]);
    }

    public function store(Request $request, CertificationScheme $scheme, ApplicationSubmissionService $service)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'applicant_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['required', 'string', 'max:30'],
        ]);

        $data['form_version'] = $scheme->form_version;
        $app = $service->createDraft($request->user()->id, $scheme->id, $data);

        return redirect()->route('client.applications.edit', $app)
            ->with('success', 'Draft permohonan dibuat. Isi setiap tahap secara bertahap.');
    }

    public function edit(Request $request, CertificationApplication $application, DynamicFormService $forms, WorkflowService $workflow, GisFormService $gisForms)
    {
        $this->own($request, $application);
        if (! $application->canBeEditedByClient()) {
            return redirect()->route('client.applications.show', $application)
                ->with('info', 'Permohonan ini tidak sedang dalam tahap pengisian atau revisi sehingga tidak dapat diubah lagi. Anda sedang melihat halaman ringkasan.');
        }

        if ($application->status === 'revision_requested') {
            $application = $workflow->transition($application, 'client_revision', 'client_open_revision', 'Klien mulai memperbaiki item revisi.', $request->user()->id);
        }

        $application->load(['scheme.sections.fields.options', 'scheme.requiredDocuments', 'values', 'documents.currentVersion', 'revisions']);
        $application->setRelation('scheme', $forms->schemeForApplication($application));

        // Khusus skema SNI (LSPro): dropdown bertingkat Produk -> Kategori dari taksonomi.
        $productGroups = $application->scheme->review_template === 'sni'
            ? \App\Models\SniProductGroup::with('categories')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            : null;

        $usesGisForms = $gisForms->schemeUsesGisForms($application->certification_scheme_id);
        $gisFormUnlocked = $gisForms->isUnlocked($application);

        return view('client.applications.edit', [
            'application' => $application,
            'values' => $forms->values($application),
            /*
             * Dua daftar yang berbeda peran, sengaja tidak digabung:
             * allDocuments dicetak seluruhnya ke HTML supaya slot bersyarat bisa
             * muncul seketika saat isian diubah, sedangkan applicableDocuments
             * menentukan mana yang terlihat pada pemuatan pertama. Yang menentukan
             * kelengkapan saat submit tetap perhitungan di server
             * (ApplicationSubmissionService), bukan keadaan tampilan ini.
             */
            'allDocuments' => $application->scheme->requiredDocuments->where('is_active', true),
            'applicableDocuments' => $forms->applicableDocuments($application->scheme, $forms->values($application)),
            'completion' => $forms->completion($application),
            'productGroups' => $productGroups,
            'usesGisForms' => $usesGisForms,
            'gisFormRequest' => $gisForms->latestRequest($application),
            'gisFormUnlocked' => $gisFormUnlocked,
            // Template hanya diambil bila sudah boleh dibagikan, agar daftarnya
            // tidak bocor sebelum permintaan klien disetujui.
            'gisFormTemplates' => $usesGisForms && $gisFormUnlocked
                ? $gisForms->templatesForScheme($application->certification_scheme_id)
                : collect(),
        ]);
    }

    public function update(Request $request, CertificationApplication $application, DynamicFormService $forms, ApplicationSubmissionService $service)
    {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);

        $input = (array) $request->input('fields', []);
        $application->loadMissing('scheme');
        $application->setRelation('scheme', $forms->schemeForApplication($application));
        $this->processUploadedFieldFiles($request, $application, $input);
        validator(
            ['fields' => $input],
            $forms->validationRules($application->scheme, $input, false),
            $forms->validationMessages(),
            $forms->validationAttributes($application->scheme, $input)
        )->validate();
        $service->saveValues($application, $input, $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'saved_at' => now()->toIso8601String(),
                'completion' => $forms->completion($application),
            ]);
        }

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    public function uploadFieldFile(Request $request, CertificationApplication $application, DynamicFormService $forms, ApplicationSubmissionService $service, AuditLogger $audit)
    {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);

        $request->validate([
            'field_code' => ['required', 'string'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $code = (string) $request->input('field_code');
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        $path = $file->storeAs(
            "applications/{$application->id}/fields/{$code}",
            time() . '_' . $originalName,
            'private'
        );

        $fileData = [
            'path' => $path,
            'original_name' => $originalName,
            'size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
        ];

        $service->saveValues($application, [$code => $fileData], $request->user()->id);

        $audit->log('application.field_file_uploaded', $application, [], [
            'field_code' => $code,
            'original_name' => $originalName,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'File berhasil diunggah.',
                'field_code' => $code,
                'original_name' => $originalName,
                'path' => $path,
                'url' => route('secure-files.application-field-file', ['application' => $application, 'code' => $code]),
                'completion' => $forms->completion($application),
            ]);
        }

        return back()->with('success', 'File berhasil diunggah.');
    }

    /**
     * Unggah gambar tanda tangan pemohon pada bagian K Form Aplikasi ISPO.
     *
     * Path-nya langsung disimpan ke larik penanda tangan, bukan menunggu
     * autosave: rute penyaji gambar mencari berkasnya lewat application_values,
     * jadi tanpa ini pratinjau setelah unggah akan gagal dimuat. Kolom teks
     * baris tersebut sengaja digabung, bukan ditimpa, supaya Nama/Jabatan yang
     * sudah diketik tidak hilang.
     */
    public function uploadSignature(
        Request $request,
        CertificationApplication $application,
        ApplicationSubmissionService $service,
        AuditLogger $audit
    ) {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);

        $request->validate([
            'field_code' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0', 'max:2'],
            'signature' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $index = (int) $request->input('index');
        $code = (string) $request->input('field_code');

        $path = $request->file('signature')->storeAs(
            "applications/{$application->id}/signatures",
            $code.'_'.$index.'_'.time().'.'.$request->file('signature')->getClientOriginalExtension(),
            'private'
        );

        $signatories = $application->values()->where('field_code', $code)->value('value_json') ?? [];
        for ($i = 0; $i <= $index; $i++) {
            $signatories[$i] = (array) ($signatories[$i] ?? []);
        }
        $signatories[$index]['tanda_tangan'] = $path;
        ksort($signatories);

        $service->saveValues($application, [$code => array_values($signatories)], $request->user()->id);

        $audit->log('application.signature_uploaded', $application, [], [
            'field_code' => $code,
            'index' => $index,
        ]);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => route('secure-files.application-signature', [
                'application' => $application,
                'index' => $index,
            ]).'?v='.time(),
        ]);
    }

    private function processUploadedFieldFiles(Request $request, CertificationApplication $application, array &$input): void
    {
        $files = (array) $request->file('fields', []);
        $forms = app(DynamicFormService::class);
        $existingValues = $forms->values($application);

        foreach ($application->scheme->sections->flatMap->fields as $field) {
            if ($field->type === 'file') {
                if (isset($files[$field->code]) && $files[$field->code]->isValid()) {
                    $file = $files[$field->code];
                    $originalName = $file->getClientOriginalName();
                    $path = $file->storeAs(
                        "applications/{$application->id}/fields/{$field->code}",
                        time() . '_' . $originalName,
                        'private'
                    );
                    $input[$field->code] = [
                        'path' => $path,
                        'original_name' => $originalName,
                        'size' => $file->getSize(),
                        'uploaded_at' => now()->toIso8601String(),
                    ];
                } elseif (isset($existingValues[$field->code]) && !empty($existingValues[$field->code])) {
                    $input[$field->code] = $existingValues[$field->code];
                }
            }
        }
    }

    public function submit(
        Request $request,
        CertificationApplication $application,
        ApplicationSubmissionService $service,
        IspoApplicationPdfService $ispoPdf
    ) {
        $this->own($request, $application);
        abort_unless($application->canBeEditedByClient(), 403);
        $service->submit($application, $request->user()->id);

        /*
         * ISPO tidak membagikan template untuk diisi manual: Form Aplikasi
         * FrO.7201 dicetak sistem dari isian klien begitu permohonan dikirim,
         * lalu ikut dikaji Admin bersama dokumen lampirannya.
         */
        if ($application->scheme->review_template === 'ispo') {
            $ispoPdf->generate($application, $request->user()->id);
        }

        return redirect()->route('client.applications.show', $application)
            ->with('success', 'Permohonan berhasil dikirim ke tim GIS.');
    }

    public function destroy(Request $request, CertificationApplication $application, AuditLogger $audit)
    {
        $this->own($request, $application);
        abort_unless($application->canBeDeletedByClient(), 403, 'Hanya draft yang belum dikirim yang dapat dihapus.');

        // Dicatat sebelum baris hilang; activity_logs tanpa FK jadi jejak tetap ada.
        $audit->log('application.draft_deleted_by_client', $application, $application->toArray());

        // Tabel anak ikut terhapus lewat cascade FK, tapi file fisik tidak.
        Storage::disk('private')->deleteDirectory('applications/' . $application->id);
        $application->delete();

        return redirect()->route('client.applications.index')
            ->with('success', 'Draft permohonan berhasil dihapus.');
    }

    public function show(Request $request, CertificationApplication $application, DynamicFormService $forms, QrCodeService $qr, GisFormService $gisForms)
    {
        $this->own($request, $application);
        $application->load(['scheme', 'values', 'documents.currentVersion', 'revisions', 'statusHistory', 'invoice.payments', 'auditStages', 'findings.correctiveActions', 'certificateDrafts', 'certificateFinal', 'surveillanceSchedules']);

        /*
         * Token link sertifikat hanya tersimpan asli pada action_url notifikasi
         * (CertificateLinkService menyimpan hash-nya saja), jadi dari situlah
         * halaman ini mengambilnya. Password sengaja tidak pernah ditampilkan
         * di portal — Tim Teknis mengirimkannya lewat kanal terpisah.
         */
        $certificateUrl = null;
        $certificateLinkActive = false;
        $verifyUrl = null;
        $verifyQr = null;

        if ($application->certificateFinal?->certificate_number) {
            /*
             * QR mengarah ke halaman verifikasi publik, bukan ke berkas: klien
             * boleh menyebarkannya ke pihak ketiga tanpa membocorkan akses unduh.
             */
            $verifyUrl = $qr->verificationUrl($application->certificateFinal->certificate_number);
            $verifyQr = $qr->svg($verifyUrl);
        }

        /*
         * QR pelacakan muncul begitu permohonan dikirim (nomor order terbit),
         * jauh sebelum ada sertifikat. Isinya halaman publik yang sama, jadi
         * klien bisa membagikannya tanpa memberi akses ke dokumen.
         */
        $trackingUrl = null;
        $trackingQr = null;
        $trackingQrDownloadUrl = null;

        if ($application->order_number) {
            $trackingUrl = $qr->verificationUrl($application->order_number);
            $trackingQr = $qr->svg($trackingUrl, 168);
            $trackingQrDownloadUrl = route('public.qr', ['nomor' => $application->order_number]);
        }

        if ($application->certificateFinal) {
            $certificateUrl = PortalNotification::forApplication($application)
                ->where('type', 'final_available')
                ->whereNotNull('action_url')
                ->latest()
                ->value('action_url');

            $certificateLinkActive = $application->certificateShareLinks()
                ->where('link_type', 'final')
                ->latest()
                ->get()
                ->contains(fn ($link) => $link->isUsable());
        }

        return view('client.applications.show', [
            'application' => $application,
            'completion' => $forms->completion($application),
            'certificateUrl' => $certificateUrl,
            'certificateLinkActive' => $certificateLinkActive,
            'verifyUrl' => $verifyUrl,
            'verifyQr' => $verifyQr,
            'trackingUrl' => $trackingUrl,
            'trackingQr' => $trackingQr,
            'trackingQrDownloadUrl' => $trackingQrDownloadUrl,
            'usesGisForms' => $gisForms->schemeUsesGisForms($application->certification_scheme_id),
            'gisFormRequest' => $gisForms->latestRequest($application),
            'gisFormUnlocked' => $gisForms->isUnlocked($application),
            'gisFormTemplates' => $gisForms->isUnlocked($application)
                ? $gisForms->templatesForScheme($application->certification_scheme_id)
                : collect(),
        ]);
    }

    private function own(Request $request, CertificationApplication $application): void
    {
        abort_unless($application->client_id === $request->user()->id, 403);
    }
}
