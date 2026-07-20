<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Finding;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditTest extends TestCase
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
            'email' => $roleCode . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function application(User $client, string $status = 'payment_completed'): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'AUD-' . Str::random(4),
            'order_date' => today(),
        ]);
    }

    public function test_admin_dapat_menugaskan_auditor(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $app = $this->application($this->user('client'), 'admin_review');

        $this->actingAs($admin)
            ->post(route('internal.applications.audit-assignments.store', $app), [
                'auditor_id' => $auditor->id,
                'assignment_role' => 'LA',
                'stage_code' => 'all',
                'assigned_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_assignments', [
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'status' => 'assigned',
        ]);
    }

    public function test_auditor_tidak_bisa_membuka_order_yang_tidak_ditugaskan(): void
    {
        $this->seedAll();
        $auditor = $this->user('auditor');
        $app = $this->application($this->user('client'));

        $this->actingAs($auditor)
            ->get(route('audit.show', $app))
            ->assertForbidden();
    }

    public function test_alur_audit_temuan_dan_tindakan_koreksi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $client = $this->user('client');
        $app = $this->application($client, 'payment_completed');

        // Admin menugaskan auditor.
        $this->actingAs($admin)->post(route('internal.applications.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id, 'assignment_role' => 'LA', 'stage_code' => 'all', 'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        // Auditor menyimpan tahap QMS → status qms_audit.
        $this->actingAs($auditor)->post(route('audit.stage', $app), [
            'stage_code' => 'qms', 'status' => 'approved', 'audit_date' => now()->format('Y-m-d'),
            'auditor_team' => 'LA: A. Auditor',
        ])->assertRedirect();
        $this->assertSame('qms_audit', $app->refresh()->status);

        // Auditor menerbitkan temuan → status corrective_action.
        $this->actingAs($auditor)->post(route('audit.findings.store', $app), [
            'finding_number' => 'NC-01', 'finding_type' => 'minor', 'description' => 'Prosedur belum lengkap.',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect();
        $this->assertSame('corrective_action', $app->refresh()->status);
        $finding = Finding::where('application_id', $app->id)->firstOrFail();
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'finding_issued']);

        // Klien mengirim tindakan koreksi.
        $this->actingAs($client)->post(route('client.corrective-actions.store', $finding), [
            'root_cause' => 'Akar penyebab.', 'correction' => 'Koreksi.', 'corrective_action' => 'Tindakan.',
        ])->assertRedirect();
        $ca = $finding->correctiveActions()->firstOrFail();

        // Auditor menerima → temuan closed, status certificate_review.
        $this->actingAs($auditor)->post(route('audit.corrective-actions.review', $ca), [
            'status' => 'accepted', 'notes' => 'Cukup.', 'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('closed', $finding->refresh()->status);
        $this->assertSame('certificate_review', $app->refresh()->status);
    }

    public function test_review_ca_revisi_memindahkan_ke_corrective_revision(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $client = $this->user('client');
        $app = $this->application($client, 'payment_completed');

        $this->actingAs($admin)->post(route('internal.applications.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id, 'assignment_role' => 'LA', 'stage_code' => 'all', 'assigned_date' => now()->format('Y-m-d'),
        ]);
        $this->actingAs($auditor)->post(route('audit.stage', $app), [
            'stage_code' => 'qms', 'status' => 'approved', 'audit_date' => now()->format('Y-m-d'), 'auditor_team' => 'LA',
        ]);
        $this->actingAs($auditor)->post(route('audit.findings.store', $app), [
            'finding_number' => 'NC-02', 'finding_type' => 'major', 'description' => 'Temuan mayor.',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);
        $finding = Finding::where('application_id', $app->id)->firstOrFail();
        $this->actingAs($client)->post(route('client.corrective-actions.store', $finding), [
            'root_cause' => 'x', 'correction' => 'y', 'corrective_action' => 'z',
        ]);
        $ca = $finding->correctiveActions()->firstOrFail();

        $this->actingAs($auditor)->post(route('audit.corrective-actions.review', $ca), [
            'status' => 'revision', 'notes' => 'Perbaiki bukti.', 'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('corrective_revision', $app->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'ca_revision']);
    }
}
