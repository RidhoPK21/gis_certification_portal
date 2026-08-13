<?php

namespace Tests\Feature;

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
 * Keputusan akhir permohonan kini diambil Tim Teknis, bukan Admin Permohonan.
 * Admin hanya mengkaji kelengkapan administrasi lalu meneruskan.
 */
class TechnicalDecisionTest extends TestCase
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
            'name' => ucfirst($roleCode),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function application(User $client): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Keputusan',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'KEP-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Bawa permohonan sampai siap diputus Tim Teknis: kajian administrasi
     * tersimpan, diteruskan, tinjauan teknis tersimpan.
     */
    private function readyForDecision(CertificationApplication $app, User $admin, User $tech): void
    {
        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Teknis',
            'items' => [
                ['type' => 'checklist', 'code' => 'audit_mandays', 'label' => 'Mandays audit', 'status' => 'sufficient', 'notes' => 'ok'],
            ],
        ])->assertRedirect();

        $this->assertSame('technical_review', $app->refresh()->status);
    }

    private function assignLeadAuditor(CertificationApplication $app, User $tech): void
    {
        $auditor = $this->user('auditor');

        $this->actingAs($tech)->post(route('technical.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
    }

    public function test_teknis_menyetujui_dan_meneruskan_ke_finance(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $finance = $this->user('finance');
        $client = $this->user('client');
        $app = $this->application($client);

        $this->readyForDecision($app, $admin, $tech);
        $this->assignLeadAuditor($app, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Lengkap.',
        ])->assertRedirect();

        $app->refresh();
        $this->assertSame('invoice_process', $app->status);
        $this->assertNotNull($app->approved_at);
        $this->assertDatabaseHas('generated_pdfs', ['application_id' => $app->id, 'document_type' => 'application_review']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'application_approved']);
        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'type' => 'invoice_process']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'application_decided']);
    }

    public function test_menyetujui_menandai_kajian_administrasi_dan_teknis_diterima(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        $this->readyForDecision($app, $admin, $tech);
        $this->assignLeadAuditor($app, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Lengkap.',
        ])->assertRedirect();

        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'administration', 'status' => 'approved']);
        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'technical', 'status' => 'approved']);
    }

    public function test_menyetujui_ditolak_tanpa_lead_auditor(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        $this->readyForDecision($app, $admin, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->assertSame('technical_review', $app->refresh()->status);
    }

    public function test_auditor_biasa_saja_belum_cukup_untuk_menyetujui(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $auditor = $this->user('auditor');
        $app = $this->application($this->user('client'));

        $this->readyForDecision($app, $admin, $tech);

        // Anggota tim tanpa peran Lead Auditor tidak memenuhi syarat keputusan.
        AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => 'A',
            'stage_code' => 'all',
            'assigned_date' => now(),
            'status' => 'assigned',
        ]);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);
    }

    public function test_menyetujui_ditolak_tanpa_tinjauan_teknis_tersimpan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();

        $this->assignLeadAuditor($app, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->assertSame('technical_review', $app->refresh()->status);
    }

    public function test_menyetujui_ditolak_saat_masih_ada_revisi_terbuka(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        $this->readyForDecision($app, $admin, $tech);
        $this->assignLeadAuditor($app, $tech);

        $app->revisions()->create([
            'revision_round' => 1,
            'target_type' => 'field',
            'target_code' => 'company_name',
            'target_label' => 'Nama perusahaan',
            'revision_note' => 'Belum lengkap.',
            'status' => 'open',
            'requested_by' => $tech->id,
        ]);

        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);
    }

    public function test_teknis_dapat_menolak_permohonan(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $client = $this->user('client');
        $app = $this->application($client);

        $this->readyForDecision($app, $admin, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.reject', $app), [
            'action_date' => now()->format('Y-m-d'),
            'reason' => 'Dokumen tidak memenuhi.',
        ])->assertRedirect();

        $this->assertSame('rejected', $app->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'application_rejected']);
        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'technical', 'status' => 'rejected']);
    }

    public function test_teknis_dapat_meminta_revisi_ke_klien(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $client = $this->user('client');
        $app = $this->application($client);

        $this->readyForDecision($app, $admin, $tech);

        $this->actingAs($tech)->post(route('technical.reviews.revision', $app), [
            'targets' => [
                ['type' => 'field', 'code' => 'company_name', 'label' => 'Nama perusahaan', 'note' => 'Lengkapi nama resmi.'],
            ],
        ])->assertRedirect();

        $this->assertSame('revision_requested', $app->refresh()->status);
        $this->assertDatabaseHas('application_revision_items', ['application_id' => $app->id, 'target_code' => 'company_name', 'status' => 'open']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'revision_requested']);
    }

    public function test_keputusan_hanya_bisa_diambil_pada_tahap_tinjauan_teknis(): void
    {
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        // Masih admin_review, belum diteruskan.
        $this->actingAs($tech)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->actingAs($tech)->post(route('technical.reviews.reject', $app), [
            'action_date' => now()->format('Y-m-d'),
            'reason' => 'Tidak memenuhi.',
        ])->assertStatus(422);

        $this->assertSame('admin_review', $app->refresh()->status);
    }

    public function test_peran_lain_tidak_dapat_memutus(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'));

        $this->readyForDecision($app, $admin, $tech);

        $this->actingAs($admin)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $this->actingAs($this->user('finance'))->post(route('technical.reviews.reject', $app), [
            'action_date' => now()->format('Y-m-d'),
            'reason' => 'Tidak boleh.',
        ])->assertForbidden();
    }
}
