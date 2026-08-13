<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GeneratedPdf;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Auditor boleh melihat ordernya, tetapi belum boleh mengerjakan sebuah tahap
 * audit sampai Surat Tugas tahap itu diterbitkan Tim Teknis.
 */
class AuditorAssignmentLetterGateTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode).' '.Str::random(3),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function application(string $status = 'payment_completed'): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Gerbang',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'GER-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function assign(CertificationApplication $app, User $auditor): void
    {
        AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);
    }

    /**
     * Terbitkan Surat Tugas satu tahap tanpa melewati UI, agar test tahap audit
     * lain tidak perlu menjalankan seluruh alur penerbitan.
     */
    private function issueLetter(CertificationApplication $app, string $stage): AssignmentLetter
    {
        $path = 'generated/assignment-letters/'.$app->id.'/ST_'.$stage.'.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 surat');

        $pdf = GeneratedPdf::create([
            'application_id' => $app->id,
            'document_type' => 'assignment_letter',
            'template_code' => 'assignment_letter_lssm',
            'document_version' => 1,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', '%PDF-1.4 surat'),
            'source_snapshot' => [],
        ]);

        return AssignmentLetter::create([
            'application_id' => $app->id,
            'stage_code' => $stage,
            'cycle' => 0,
            'template_code' => 'lssm',
            'number_family' => 'lssm',
            'letter_number' => '001/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'pdf_version' => 1,
            'generated_pdf_id' => $pdf->id,
        ]);
    }

    private function stagePayload(string $stage): array
    {
        return [
            'stage_code' => $stage,
            'status' => 'approved',
            'audit_date' => now()->format('Y-m-d'),
            'auditor_team' => 'LA: Vera',
        ];
    }

    public function test_tanpa_surat_tugas_auditor_tidak_bisa_menyimpan_tahap(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);

        $this->actingAs($auditor)
            ->post(route('audit.stage', $app), $this->stagePayload('stage_1'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_stages', ['application_id' => $app->id, 'stage_code' => 'stage_1']);
    }

    public function test_halaman_audit_tetap_terbuka_dengan_banner_menunggu(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);

        $html = $this->actingAs($auditor)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Menunggu Surat Tugas', $html);
        $this->assertStringContainsString('<fieldset disabled', $html);
    }

    public function test_surat_satu_tahap_tidak_membuka_tahap_lain(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);
        $this->issueLetter($app, 'stage_1');

        $this->actingAs($auditor)
            ->post(route('audit.stage', $app), $this->stagePayload('stage_1'))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_stages', ['application_id' => $app->id, 'stage_code' => 'stage_1']);

        $this->actingAs($auditor)
            ->post(route('audit.stage', $app), $this->stagePayload('stage_2'))
            ->assertForbidden();
    }

    public function test_skip_tahap_juga_butuh_surat_tugas(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $scheme = CertificationScheme::where('category', 'product')->firstOrFail();
        $app = $this->application();
        $app->update(['certification_scheme_id' => $scheme->id]);
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);

        $this->actingAs($auditor)
            ->post(route('audit.stage.skip', $app), [
                'stage_code' => 'stage_1',
                'reason' => 'Klien sudah tersertifikasi sebelumnya.',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();
    }

    public function test_tindakan_koreksi_tidak_ikut_terkunci(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application('corrective_action');
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);

        /*
         * Tidak ada Surat Tugas untuk corrective_action. Yang diuji: gerbangnya
         * tidak ikut memblokir — jadi respons boleh 404/422 karena datanya belum
         * lengkap, tetapi tidak boleh 403 karena surat.
         */
        $response = $this->actingAs($auditor)
            ->post(route('audit.corrective-actions.review', 999), [
                'decision' => 'accepted',
                'notes' => 'Cukup.',
            ]);

        $this->assertNotSame(403, $response->getStatusCode(), 'Tindakan koreksi ikut terkunci oleh gerbang Surat Tugas.');
    }

    public function test_superadmin_melewati_gerbang(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('superadmin'))
            ->post(route('audit.stage', $app), $this->stagePayload('stage_1'))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_stages', ['application_id' => $app->id, 'stage_code' => 'stage_1']);
    }

    public function test_menyelesaikan_audit_juga_butuh_surat_qms(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application('qms_audit');
        $auditor = $this->user('auditor');
        $this->assign($app, $auditor);

        $this->actingAs($auditor)
            ->post(route('audit.complete', $app), ['notes' => 'Selesai.'])
            ->assertForbidden();
    }
}
