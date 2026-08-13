<?php

namespace App\Http\Controllers;

use App\Models\AssignmentLetter;
use App\Models\AuditStageFile;
use App\Models\CertificationApplication;
use App\Models\CorrectiveActionFile;
use App\Models\GisFormTemplate;
use App\Models\Invoice;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use App\Services\GisFormService;
use Illuminate\Http\Request;

class SecureFileController extends Controller
{
    /**
     * Peran internal yang berkepentingan atas berkas sebuah order.
     *
     * Sustain ikut karena seluruh rantai ISPO — tinjauan sampai sertifikat —
     * dikerjakan tim itu. Daftar ini hanya menyatakan "peran yang berurusan
     * dengan order"; pembatasan skemanya ditegakkan internalCanAccess().
     */
    private const INTERNAL_ROLES = [
        'admin_application', 'admin_sustain',
        'technical', 'technical_sustain',
        'superadmin',
    ];

    /**
     * Internal boleh mengunduh berkas hanya pada skema yang ditanganinya.
     *
     * Tanpa pemeriksaan kepemilikan di sini, order ISPO tetap dapat diambil
     * berkasnya lewat URL berkas oleh tim non-Sustain — halaman ordernya
     * dijaga middleware scheme.owner, tetapi route berkas berada di luar grup itu.
     */
    private function internalCanAccess(Request $request, CertificationApplication $application): bool
    {
        return $request->user()->hasRole(self::INTERNAL_ROLES)
            && $application->isHandledBy($request->user());
    }

    /**
     * Apakah pengguna adalah auditor yang ditugaskan pada permohonan ini.
     *
     * Sengaja dipisah dan selalu di-OR-kan dengan hak akses peran lain: bentuk
     * lama menimpa hasil pemeriksaan sebelumnya, sehingga akun yang memegang
     * peran technical sekaligus auditor justru kehilangan akses pada order yang
     * tidak ditugaskan kepadanya.
     */
    private function isAssignedAuditor(Request $request, CertificationApplication $application): bool
    {
        return $request->user()->hasRole('auditor')
            && $application->auditAssignments()
                ->where('auditor_id', $request->user()->id)
                ->where('status', 'assigned')
                ->exists();
    }

    public function invoice(Request $request, Invoice $invoice, FileStorageService $files, AuditLogger $audit)
    {
        $invoice->load('application');
        $allowed = $invoice->application->client_id === $request->user()->id
            || $request->user()->hasRole('finance')
            || $this->internalCanAccess($request, $invoice->application);
        abort_unless($allowed && filled($invoice->file_path), 403);
        $audit->log('file.invoice_downloaded', $invoice);

        return $files->response($invoice->file_path, 'invoice-'.$invoice->invoice_number.'.'.pathinfo($invoice->file_path, PATHINFO_EXTENSION));
    }

    public function auditReport(Request $request, AuditStageFile $file, FileStorageService $files, AuditLogger $audit)
    {
        $file->load('auditStage.application');
        $application = $file->auditStage->application;
        $allowed = $this->internalCanAccess($request, $application)
            || $this->isAssignedAuditor($request, $application);
        abort_unless($allowed, 403);
        $audit->log('file.audit_report_downloaded', $file, [], ['application_id' => $application->id]);

        return $files->response($file->file_path, $file->original_name);
    }

    public function correctiveAction(Request $request, CorrectiveActionFile $file, FileStorageService $files, AuditLogger $audit)
    {
        $file->load('correctiveAction.finding.application');
        $application = $file->correctiveAction->finding->application;
        $allowed = $application->client_id === $request->user()->id
            || $this->internalCanAccess($request, $application)
            || $this->isAssignedAuditor($request, $application);
        abort_unless($allowed, 403);
        $audit->log('file.corrective_action_downloaded', $file, [], ['application_id' => $application->id]);

        return $files->response($file->file_path, $file->original_name);
    }

    /**
     * Template Formulir Wajib GIS hanya boleh diunduh oleh internal, atau oleh
     * klien yang permintaan templatenya sudah disetujui pada skema yang sama.
     */
    public function gisFormTemplate(Request $request, GisFormTemplate $template, FileStorageService $files, AuditLogger $audit, GisFormService $gisForms)
    {
        /*
         * Tanpa penyaringan skema: berkas ini template kosong milik skema,
         * bukan data order, jadi seluruh internal boleh mengunduhnya.
         */
        $user = $request->user();
        $allowed = $user->hasRole(self::INTERNAL_ROLES);

        if (! $allowed && $user->hasRole('client')) {
            $allowed = CertificationApplication::query()
                ->where('client_id', $user->id)
                ->where('certification_scheme_id', $template->certification_scheme_id)
                ->get()
                ->contains(fn (CertificationApplication $application) => $gisForms->isUnlocked($application));
        }

        abort_unless($allowed && $template->is_active, 403);
        $audit->log('file.gis_form_template_downloaded', $template, [], ['code' => $template->code]);

        return $files->response($template->file_path, $template->original_name);
    }

    /**
     * Pratinjau tanda tangan berstempel yang menempel pada satu Surat Tugas.
     *
     * Berbeda dari tanda tangan profil: berkas ini milik surat, bukan milik
     * akun, sehingga hanya internal yang menyiapkan surat yang boleh melihatnya.
     */
    public function assignmentLetterSignature(Request $request, AssignmentLetter $letter, FileStorageService $files, AuditLogger $audit)
    {
        $letter->load('application');
        abort_unless($this->internalCanAccess($request, $letter->application), 403);
        abort_unless(filled($letter->signature_path), 404);

        $audit->log('file.assignment_letter_signature_viewed', $letter, [], [
            'application_id' => $letter->application_id,
            'stage' => $letter->stage_code,
        ]);

        return $files->response(
            $letter->signature_path,
            'tanda-tangan-'.$letter->stage_code.'.'.pathinfo($letter->signature_path, PATHINFO_EXTENSION),
            'inline'
        );
    }

    public function fieldFile(Request $request, CertificationApplication $application, string $code, FileStorageService $files, AuditLogger $audit)
    {
        $allowed = $application->client_id === $request->user()->id
            || $this->internalCanAccess($request, $application)
            || $this->isAssignedAuditor($request, $application);
        abort_unless($allowed, 403);

        $value = $application->values()->where('field_code', $code)->first();
        abort_unless($value && $value->value_json, 404);

        $data = $value->value_json;
        $path = $data['path'] ?? null;
        $name = $data['original_name'] ?? 'file';
        abort_unless($path, 404);

        $audit->log('file.application_field_file_downloaded', $application, [], ['field_code' => $code, 'original_name' => $name]);

        return $files->response($path, $name);
    }

    /**
     * Gambar tanda tangan pemohon pada bagian K Form Aplikasi ISPO.
     *
     * Path-nya tersimpan di dalam larik penanda tangan, bukan sebagai nilai
     * field tersendiri, sehingga dicari berdasarkan urutan penanda tangan.
     */
    public function applicationSignature(Request $request, CertificationApplication $application, int $index, FileStorageService $files, AuditLogger $audit)
    {
        $allowed = $application->client_id === $request->user()->id
            || $this->internalCanAccess($request, $application)
            || $this->isAssignedAuditor($request, $application);
        abort_unless($allowed, 403);

        $signatories = $application->values()
            ->where('field_code', 'signatories')
            ->value('value_json') ?? [];

        $path = $signatories[$index]['tanda_tangan'] ?? null;
        abort_unless($path, 404);

        $audit->log('file.application_signature_viewed', $application, [], ['index' => $index]);

        return $files->response($path, 'tanda-tangan-'.($index + 1).'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
