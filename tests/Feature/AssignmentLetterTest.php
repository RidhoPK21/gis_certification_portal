<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
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
 * Halaman Penugasan & Surat Tugas milik Tim Teknis: akses, penyimpanan draft,
 * penerbitan PDF, dan penggantian auditor setelah tahap Finance.
 */
class AssignmentLetterTest extends TestCase
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

    private function application(string $schemeCode = 'ISO9001', string $status = 'payment_completed'): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Penugasan',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'PEN-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function assign(CertificationApplication $app, string $role = 'LA', string $stage = 'all'): User
    {
        $auditor = $this->user('auditor');

        AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => $role,
            'stage_code' => $stage,
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        return $auditor;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CertificationApplication $app, array $overrides = []): array
    {
        $rows = app(\App\Services\AssignmentLetterService::class)->auditorRows($app->fresh()->load('auditAssignments.auditor'), 'stage_1');

        $auditors = [];
        foreach ($rows as $i => $row) {
            $auditors[$i] = [
                'include' => '1',
                'auditor_id' => $row['auditor_id'],
                'name' => $row['name'],
                'role_code' => $row['role_code'],
                'position_label' => $row['position_label'],
            ];
        }

        return array_merge([
            'letter_number' => '007/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => now()->format('Y-m-d'),
            'assignment_start_date' => now()->addDays(7)->format('Y-m-d'),
            'assignment_end_date' => now()->addDays(8)->format('Y-m-d'),
            'signer_name' => 'Prima Sulistya',
            'signer_position' => 'Manajer Teknis',
            'fields' => ['company_name' => 'PT Penugasan', 'industry_scope' => 'Beton'],
            'auditors' => $auditors,
        ], $overrides);
    }

    public function test_teknis_membuka_daftar_penugasan_peran_lain_ditolak(): void
    {
        $this->seedAll();
        $this->application();

        $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.index'))
            ->assertOk()
            ->assertSee('Penugasan');

        $this->actingAs($this->user('finance'))
            ->get(route('technical.assignments.index'))
            ->assertForbidden();

        $this->actingAs($this->user('auditor'))
            ->get(route('technical.assignments.index'))
            ->assertForbidden();
    }

    public function test_halaman_order_menolak_order_yang_belum_lunas(): void
    {
        $this->seedAll();
        $app = $this->application('ISO9001', 'technical_review');

        $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.show', $app))
            ->assertStatus(422);
    }

    public function test_halaman_order_menampilkan_ketiga_tahap(): void
    {
        $this->seedAll();
        $app = $this->application();
        $this->assign($app);

        $html = $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.show', $app))
            ->assertOk()
            ->getContent();

        foreach (AssignmentLetter::STAGES as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_menyimpan_draft_membuat_satu_baris_per_tahap(): void
    {
        $this->seedAll();
        $app = $this->application();
        $this->assign($app);
        $tech = $this->user('technical');

        $this->actingAs($tech)
            ->post(route('technical.assignments.save', [$app, 'stage_1']), $this->payload($app))
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_letters', [
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
            'letter_number' => '007/ST/GIS-LSSM/MT/VIII/2026',
        ]);
        $this->assertSame(1, AssignmentLetter::where('application_id', $app->id)->count());

        // Simpan ulang harus memperbarui, bukan menambah baris (kunci unik).
        $this->actingAs($tech)
            ->post(route('technical.assignments.save', [$app, 'stage_1']), $this->payload($app, [
                'letter_number' => '008/ST/GIS-LSSM/MT/VIII/2026',
            ]))
            ->assertRedirect();

        $this->assertSame(1, AssignmentLetter::where('application_id', $app->id)->count());
        $this->assertSame('008/ST/GIS-LSSM/MT/VIII/2026', AssignmentLetter::where('application_id', $app->id)->value('letter_number'));
    }

    public function test_menerbitkan_surat_membuat_pdf_dan_menandai_terbit(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $this->assign($app);
        $tech = $this->user('technical');

        $this->actingAs($tech)
            ->post(route('technical.assignments.generate', [$app, 'stage_1']), $this->payload($app))
            ->assertRedirect();

        $letter = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_1')->firstOrFail();

        $this->assertNotNull($letter->generated_pdf_id);
        $this->assertSame(1, $letter->pdf_version);
        $this->assertTrue($app->fresh()->hasAssignmentLetter('stage_1'));
        $this->assertFalse($app->fresh()->hasAssignmentLetter('stage_2'));

        $this->assertDatabaseHas('generated_pdfs', [
            'application_id' => $app->id,
            'document_type' => 'assignment_letter',
        ]);
    }

    public function test_menerbitkan_ditolak_tanpa_auditor(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.generate', [$app, 'stage_1']), $this->payload($app, ['auditors' => []]))
            ->assertStatus(422);

        $this->assertDatabaseMissing('generated_pdfs', [
            'application_id' => $app->id,
            'document_type' => 'assignment_letter',
        ]);
    }

    public function test_tahap_tidak_dikenal_ditolak(): void
    {
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.save', [$app, 'corrective_action']), $this->payload($app))
            ->assertNotFound();
    }

    public function test_surat_membekukan_tim_sehingga_perubahan_auditor_tidak_mengubah_surat_terbit(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $first = $this->assign($app);
        $tech = $this->user('technical');

        $this->actingAs($tech)
            ->post(route('technical.assignments.generate', [$app, 'stage_1']), $this->payload($app))
            ->assertRedirect();

        $letter = AssignmentLetter::where('application_id', $app->id)->firstOrFail();
        $frozen = $letter->auditor_rows;
        $this->assertSame($first->id, $frozen[0]['auditor_id']);

        // Auditor diganti setelah surat terbit.
        $this->actingAs($tech)
            ->delete(route('technical.audit-assignments.destroy', AuditAssignment::where('application_id', $app->id)->firstOrFail()))
            ->assertRedirect();
        $second = $this->assign($app);

        $this->assertSame($frozen, $letter->refresh()->auditor_rows, 'Tabel auditor surat yang sudah terbit ikut berubah.');
        $this->assertNotSame($second->id, $letter->auditor_rows[0]['auditor_id']);
    }

    public function test_tahap_boleh_punya_tim_berbeda(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');

        $stageOneAuditor = $this->assign($app, 'LA', 'stage_1');
        $stageTwoAuditor = $this->assign($app, 'LA', 'stage_2');

        $service = app(\App\Services\AssignmentLetterService::class);
        $app->load('auditAssignments.auditor');

        $rowsOne = $service->auditorRows($app, 'stage_1');
        $rowsTwo = $service->auditorRows($app, 'stage_2');

        $this->assertSame([$stageOneAuditor->id], array_column($rowsOne, 'auditor_id'));
        $this->assertSame([$stageTwoAuditor->id], array_column($rowsTwo, 'auditor_id'));
    }

    public function test_generate_ulang_pdf_tinjauan_menaikkan_versi(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $this->assign($app);

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.regenerate-review', $app))
            ->assertRedirect();

        $this->assertDatabaseHas('generated_pdfs', [
            'application_id' => $app->id,
            'document_type' => 'application_review',
            'document_version' => 1,
        ]);

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.regenerate-review', $app))
            ->assertRedirect();

        $this->assertDatabaseHas('generated_pdfs', [
            'application_id' => $app->id,
            'document_type' => 'application_review',
            'document_version' => 2,
        ]);
    }

    public function test_panelis_dapat_dipilih_ulang_setelah_tahap_tinjauan(): void
    {
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');
        $panelist = $this->user('technical');

        $app->reviews()->create([
            'review_type' => 'technical',
            'round' => 1,
            'status' => 'approved',
            'action_date' => today(),
        ]);

        $this->actingAs($tech)
            ->post(route('technical.reviews.panelists', $app), ['panelist_ids' => [$panelist->id]])
            ->assertRedirect();

        $review = $app->reviews()->where('review_type', 'technical')->firstOrFail();
        $this->assertSame([$panelist->id], $review->panelist_ids);
    }
}
