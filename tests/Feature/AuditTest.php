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

    /**
     * Skema produk (SNI/LSPro) satu-satunya yang mengizinkan skip Stage 1
     * maupun Stage 2 — lihat WorkflowSeeder.
     */
    private function productApplication(User $client): CertificationApplication
    {
        $scheme = CertificationScheme::where('category', 'product')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'payment_completed',
            'current_step' => 'payment_completed',
            'company_name' => 'PT Produk',
            'contact_email' => 'produk@uji.test',
            'order_number' => 'PRD-'.Str::random(4),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function assignAuditor(CertificationApplication $app, User $admin, User $auditor): void
    {
        $this->actingAs($admin)->post(route('internal.applications.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id, 'assignment_role' => 'LA', 'stage_code' => 'all', 'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
    }

    public function test_form_skip_dinonaktifkan_untuk_skema_yang_mewajibkan_stage(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $app = $this->application($this->user('client'));
        $this->assignAuditor($app, $admin, $auditor);

        $html = $this->actingAs($auditor)->get(route('audit.show', $app))->assertOk()->getContent();

        $this->assertStringContainsString('wajib dilaksanakan untuk skema sistem manajemen', $html);
        $this->assertStringContainsString('<fieldset disabled', $html);
    }

    public function test_skip_ditolak_untuk_skema_yang_mewajibkan_stage(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $app = $this->application($this->user('client'));
        $this->assignAuditor($app, $admin, $auditor);

        $this->actingAs($auditor)->post(route('audit.stage.skip', $app), [
            'stage_code' => 'stage_1',
            'reason' => 'Klien sudah tersertifikasi sebelumnya.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('audit_stages', ['application_id' => $app->id, 'status' => 'skipped']);
    }

    public function test_skip_berhasil_untuk_skema_produk(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $app = $this->productApplication($this->user('client'));
        $this->assignAuditor($app, $admin, $auditor);
        app(\App\Services\WorkflowService::class)->initialize($app);

        $this->actingAs($auditor)->post(route('audit.stage.skip', $app), [
            'stage_code' => 'stage_1',
            'reason' => 'Produk sudah pernah diaudit pada siklus sebelumnya.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_stages', [
            'application_id' => $app->id, 'stage_code' => 'stage_1', 'status' => 'skipped',
        ]);
        $this->assertSame('stage_2_audit', $app->refresh()->status);
    }

    public function test_skip_menyembuhkan_order_tanpa_baris_workflow(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        // Sengaja tanpa WorkflowService::initialize(), meniru order lama yang
        // di-submit sebelum WorkflowSeeder pernah dijalankan.
        $app = $this->productApplication($this->user('client'));
        $this->assignAuditor($app, $admin, $auditor);

        $this->assertDatabaseMissing('application_workflow_steps', ['application_id' => $app->id]);

        $this->actingAs($auditor)->post(route('audit.stage.skip', $app), [
            'stage_code' => 'stage_1',
            'reason' => 'Order lama tanpa baris workflow.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertDatabaseHas('application_workflow_steps', ['application_id' => $app->id]);
        $this->assertDatabaseHas('audit_stages', [
            'application_id' => $app->id, 'stage_code' => 'stage_1', 'status' => 'skipped',
        ]);
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

    public function test_dashboard_dan_keamanan_berdasarkan_assignment_dan_scope(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditorAssigned = $this->user('auditor');
        $auditorUnassigned = $this->user('auditor');
        $auditorStage1 = $this->user('auditor');
        $auditorCA = $this->user('auditor');
        $client = $this->user('client');

        $app = $this->application($client, 'payment_completed');

        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditorAssigned->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);

        // 1. Auditor yang ditugaskan dapat melihat payment_completed pada Dashboard.
        $response = $this->actingAs($auditorAssigned)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee($app->order_number);
        $statsAssigned = $response->viewData('stats');
        $this->assertSame(1, $statsAssigned['all']);
        $this->assertSame(1, $statsAssigned['queue']);

        // 2. Auditor yang tidak ditugaskan tidak dapat melihat permohonan tersebut pada daftar maupun statistik Dashboard.
        $responseUnassigned = $this->actingAs($auditorUnassigned)->get(route('dashboard'));
        $responseUnassigned->assertOk();
        $responseUnassigned->assertDontSee($app->order_number);
        $statsUnassigned = $responseUnassigned->viewData('stats');
        $this->assertSame(0, $statsUnassigned['all']);
        $this->assertSame(0, $statsUnassigned['queue']);

        // 3. Assignment stage_1 dapat melihat payment_completed dan stage_1_audit.
        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditorStage1->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'stage_1',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);
        $this->actingAs($auditorStage1)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($app->order_number);
        $app->update(['status' => 'stage_1_audit']);
        $this->actingAs($auditorStage1)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($app->order_number);
        $app->update(['status' => 'stage_2_audit']);
        $this->actingAs($auditorStage1)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($app->order_number);

        // 4. Assignment corrective_action hanya melihat corrective_action dan corrective_revision.
        $app2 = $this->application($client, 'payment_completed');
        $app2->update(['order_number' => 'AUD-CA01']);
        \App\Models\AuditAssignment::create([
            'application_id' => $app2->id,
            'auditor_id' => $auditorCA->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'corrective_action',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);
        $this->actingAs($auditorCA)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('AUD-CA01');
        $app2->update(['status' => 'corrective_action']);
        $this->actingAs($auditorCA)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AUD-CA01');
        $app2->update(['status' => 'corrective_revision']);
        $this->actingAs($auditorCA)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('AUD-CA01');

        // 5. Auditor yang tidak ditugaskan tetap mendapat 403 ketika membuka detail.
        $this->actingAs($auditorUnassigned)
            ->get(route('audit.show', $app))
            ->assertForbidden();

        // 6. Auditor yang tidak ditugaskan tetap mendapat 403 ketika mengunduh dokumen, laporan audit, atau bukti Corrective Action.
        $doc = \App\Models\ApplicationDocument::create([
            'application_id' => $app->id,
            'document_code' => 'DOC-TEST',
            'document_name' => 'Dokumen Test',
        ]);
        $this->actingAs($auditorUnassigned)
            ->get(route('secure-files.application-document', $doc))
            ->assertForbidden();

        $stage = \App\Models\AuditStage::create([
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
            'status' => 'approved',
        ]);
        $stageFile = \App\Models\AuditStageFile::create([
            'audit_stage_id' => $stage->id,
            'original_name' => 'report.pdf',
            'file_path' => 'private/report.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'checksum_sha256' => hash('sha256', 'test'),
        ]);
        $this->actingAs($auditorUnassigned)
            ->get(route('secure-files.audit', $stageFile))
            ->assertForbidden();

        $finding = \App\Models\Finding::create([
            'application_id' => $app->id,
            'finding_number' => 'NC-TEST',
            'description' => 'Test Finding',
        ]);
        $ca = \App\Models\CorrectiveAction::create([
            'finding_id' => $finding->id,
            'status' => 'submitted',
        ]);
        $caFile = \App\Models\CorrectiveActionFile::create([
            'corrective_action_id' => $ca->id,
            'original_name' => 'ca.pdf',
            'file_path' => 'private/ca.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'checksum_sha256' => hash('sha256', 'test'),
        ]);
        $this->actingAs($auditorUnassigned)
            ->get(route('secure-files.corrective-action', $caFile))
            ->assertForbidden();
    }

    public function test_assignment_scope_authorization_and_corrective_action_tabs(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $app = $this->application($client, 'stage_1_audit');

        $auditorAll = $this->user('auditor');
        $auditorStage1 = $this->user('auditor');
        $auditorStage2 = $this->user('auditor');
        $auditorQms = $this->user('auditor');
        $auditorCA = $this->user('auditor');
        $auditorUnassigned = $this->user('auditor');

        foreach ([
            [$auditorAll, 'all'],
            [$auditorStage1, 'stage_1'],
            [$auditorStage2, 'stage_2'],
            [$auditorQms, 'qms'],
            [$auditorCA, 'corrective_action'],
        ] as [$aud, $scope]) {
            \App\Models\AuditAssignment::create([
                'application_id' => $app->id,
                'auditor_id' => $aud->id,
                'assigned_by' => $admin->id,
                'assignment_role' => 'LA',
                'stage_code' => $scope,
                'status' => 'assigned',
                'assigned_date' => today(),
            ]);
        }

        // 1. Auditor scope all dapat membuka seluruh tab
        $this->actingAs($auditorAll)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Ringkasan')
            ->assertSee('Stage 1')
            ->assertSee('Stage 2')
            ->assertSee('QMS/Lapangan')
            ->assertSee('Corrective Action')
            ->assertSee('Pencatatan Audit Stage 1')
            ->assertSee('Pencatatan Audit Stage 2')
            ->assertSee('Pencatatan Audit QMS / Audit Lapangan');

        // 2. Auditor scope stage_1 dapat melihat Ringkasan & Stage 1, tapi tidak melihat form stage lain
        $this->actingAs($auditorStage1)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Pencatatan Audit Stage 1')
            ->assertSee('Bagian ini tidak termasuk dalam lingkup penugasan Anda.');

        // dan ditolak (403) jika mengirim stage_2 atau qms
        $this->actingAs($auditorStage1)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_2',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertForbidden();

        $this->actingAs($auditorStage1)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'qms',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertForbidden();

        // 3. Auditor scope stage_2 bisa mengirim stage_2, tapi ditolak untuk stage_1
        $this->actingAs($auditorStage2)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_1',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertForbidden();

        $this->actingAs($auditorStage2)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_2',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertRedirect();

        // 4. Auditor scope qms bisa melakukan qms, tidak bisa stage_1
        $this->actingAs($auditorQms)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_1',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertForbidden();

        $this->actingAs($auditorQms)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'qms',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertRedirect();

        // 5. Auditor scope corrective_action dapat membuka Corrective Action, dilarang submit stage 1
        $this->actingAs($auditorCA)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_1',
                'status' => 'approved',
                'audit_date' => '2026-07-28',
                'auditor_team' => 'LA: Test',
            ])
            ->assertForbidden();

        // 6. Auditor tanpa assignment mendapat 403
        $this->actingAs($auditorUnassigned)
            ->get(route('audit.show', $app))
            ->assertForbidden();

        // 7. Corrective Action tanpa temuan menampilkan status bahwa tindakan koreksi tidak diperlukan
        $this->actingAs($auditorCA)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Tidak ada temuan pada permohonan ini. Tindakan koreksi tidak diperlukan.');

        // Update status ke qms_audit untuk mengetes temuan & CA
        $app->update(['status' => 'qms_audit']);

        // 8. Buat temuan oleh QMS auditor
        $this->actingAs($auditorQms)
            ->post(route('audit.findings.store', $app), [
                'finding_number' => 'NC-TEST-01',
                'finding_type' => 'minor',
                'clause_reference' => '7.1',
                'description' => 'Temuan Minor Test',
                'due_date' => now()->addDays(14)->format('Y-m-d'),
            ])
            ->assertRedirect();

        $finding = $app->findings()->firstOrFail();

        // Lihat di CA tab: Ada temuan tetapi Klien belum menjawab
        $this->actingAs($auditorCA)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Menunggu tindakan koreksi dari Klien.')
            ->assertDontSee('Simpan Review');

        // 9. Klien mengirim jawaban CA
        $ca = \App\Models\CorrectiveAction::create([
            'finding_id' => $finding->id,
            'root_cause' => 'Kurang pelatihan',
            'correction' => 'Dilakukan briefing',
            'corrective_action' => 'SOP diperbarui',
            'status' => 'submitted',
            'submitted_at' => now(),
            'revision' => 1,
        ]);

        $this->actingAs($auditorCA)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Kurang pelatihan')
            ->assertSee('Dilakukan briefing')
            ->assertSee('SOP diperbarui')
            ->assertSee('Simpan Review');

        // Auditor stage_1 tidak bisa melakukan review CA -> 403
        $this->actingAs($auditorStage1)
            ->post(route('audit.corrective-actions.review', $ca), [
                'status' => 'accepted',
                'notes' => 'OK',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();

        // 11. Review perlu revisi
        $this->actingAs($auditorCA)
            ->post(route('audit.corrective-actions.review', $ca), [
                'status' => 'revision',
                'notes' => 'Tolong perbaiki lagi SOP-nya',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('corrective_actions', [
            'id' => $ca->id,
            'status' => 'revision',
        ]);
        $this->assertDatabaseHas('findings', [
            'id' => $finding->id,
            'status' => 'revision',
        ]);
        $this->assertDatabaseHas('corrective_action_reviews', [
            'corrective_action_id' => $ca->id,
            'status' => 'revision',
            'notes' => 'Tolong perbaiki lagi SOP-nya',
        ]);

        // 10. Klien kirim revisi & Review Diterima
        $ca2 = \App\Models\CorrectiveAction::create([
            'finding_id' => $finding->id,
            'root_cause' => 'Kurang pelatihan mendalam',
            'correction' => 'Dilakukan briefing lengkap',
            'corrective_action' => 'SOP diperbarui dan disahkan',
            'status' => 'submitted',
            'submitted_at' => now(),
            'revision' => 2,
        ]);

        $this->actingAs($auditorCA)
            ->post(route('audit.corrective-actions.review', $ca2), [
                'status' => 'accepted',
                'notes' => 'Sudah cukup dan diterima',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('corrective_actions', [
            'id' => $ca2->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('findings', [
            'id' => $finding->id,
            'status' => 'closed',
        ]);

        // Cek tab CA setelah semua temuan closed
        $this->actingAs($auditorCA)
            ->get(route('audit.show', $app))
            ->assertOk()
            ->assertSee('Seluruh tindakan koreksi telah diterima.');
    }

    public function test_qms_completion_validations_and_scope(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $app = $this->application($client, 'qms_audit');

        $auditorQms = $this->user('auditor');
        $auditorCA = $this->user('auditor');

        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditorQms->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'qms',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);
        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditorCA->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'Auditor',
            'stage_code' => 'corrective_action',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);

        // 1. QMS belum dibuat tidak dapat diselesaikan, respons 422
        $this->actingAs($auditorQms)
            ->post(route('audit.complete', $app), ['notes' => 'Test', 'action_date' => now()->format('Y-m-d')])
            ->assertStatus(422);

        // 2. QMS uploaded tidak dapat diselesaikan, respons 422
        $qmsStage = \App\Models\AuditStage::create([
            'application_id' => $app->id,
            'stage_code' => 'qms',
            'status' => 'uploaded',
            'audit_date' => today(),
            'updated_by' => $auditorQms->id,
        ]);
        $this->actingAs($auditorQms)
            ->post(route('audit.complete', $app), ['notes' => 'Test', 'action_date' => now()->format('Y-m-d')])
            ->assertStatus(422);

        // 3. QMS revision tidak dapat diselesaikan, respons 422
        $qmsStage->update(['status' => 'revision']);
        $this->actingAs($auditorQms)
            ->post(route('audit.complete', $app), ['notes' => 'Test', 'action_date' => now()->format('Y-m-d')])
            ->assertStatus(422);

        // 5. QMS approved dengan temuan open tidak dapat diselesaikan, respons 422
        $qmsStage->update(['status' => 'approved']);
        $finding = \App\Models\Finding::create([
            'application_id' => $app->id,
            'finding_number' => 'NC-01',
            'finding_type' => 'minor',
            'description' => 'Test open finding',
            'due_date' => now()->addDays(14),
            'status' => 'open',
            'created_by' => $auditorQms->id,
        ]);
        $this->actingAs($auditorQms)
            ->post(route('audit.complete', $app), ['notes' => 'Test', 'action_date' => now()->format('Y-m-d')])
            ->assertStatus(422);

        // 6. Auditor scope corrective_action tanpa scope qms tidak dapat memanggil completeAudit() -> 403
        $finding->update(['status' => 'closed']);
        $this->actingAs($auditorCA)
            ->post(route('audit.complete', $app), ['notes' => 'Test', 'action_date' => now()->format('Y-m-d')])
            ->assertForbidden();

        // 4. QMS approved tanpa temuan dapat berubah menjadi certificate_review
        $this->actingAs($auditorQms)
            ->post(route('audit.complete', $app), ['notes' => 'Selesai audit', 'action_date' => now()->format('Y-m-d')])
            ->assertRedirect();
        $this->assertSame('certificate_review', $app->refresh()->status);
    }

    public function test_auditor_index_scope_and_assignment_filtering(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');

        $appQms = $this->application($client, 'qms_audit');
        $appStage1 = $this->application($client, 'stage_1_audit');
        $appCA = $this->application($client, 'corrective_action');

        $auditorQms = $this->user('auditor');
        $auditorCA = $this->user('auditor');
        $auditorUnassigned = $this->user('auditor');

        \App\Models\AuditAssignment::create([
            'application_id' => $appQms->id,
            'auditor_id' => $auditorQms->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'qms',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);
        \App\Models\AuditAssignment::create([
            'application_id' => $appStage1->id,
            'auditor_id' => $auditorQms->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'qms',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);
        \App\Models\AuditAssignment::create([
            'application_id' => $appCA->id,
            'auditor_id' => $auditorCA->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'Auditor',
            'stage_code' => 'corrective_action',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);

        // 7. Auditor tidak ditugaskan tidak melihat order pada /internal/audit
        $this->actingAs($auditorUnassigned)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertDontSee($appQms->order_number)
            ->assertDontSee($appStage1->order_number)
            ->assertDontSee($appCA->order_number);

        // 8. Auditor scope qms hanya melihat order qms_audit yang ditugaskan
        $this->actingAs($auditorQms)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee($appQms->order_number)
            ->assertDontSee($appStage1->order_number)
            ->assertDontSee($appCA->order_number);

        // 9. Auditor scope corrective_action hanya melihat corrective_action dan corrective_revision yang ditugaskan
        $this->actingAs($auditorCA)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee($appCA->order_number)
            ->assertDontSee($appQms->order_number);

        // 10. Hash view menggunakan stage_1 dan stage_2
        $this->actingAs($auditorQms)
            ->get(route('audit.show', $appQms))
            ->assertOk()
            ->assertSee('id="stage_1"', false)
            ->assertSee('id="stage_2"', false)
            ->assertSee("['ringkasan', 'stage_1', 'stage_2', 'qms', 'corrective_action']", false);
    }

    public function test_ca_completion_sends_technical_notification_once(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $auditor = $this->user('auditor');
        $client = $this->user('client');
        $tech = $this->user('technical');
        $app = $this->application($client, 'corrective_action');

        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'LA',
            'stage_code' => 'corrective_action',
            'status' => 'assigned',
            'assigned_date' => today(),
        ]);

        $finding = \App\Models\Finding::create([
            'application_id' => $app->id,
            'finding_number' => 'NC-01',
            'finding_type' => 'minor',
            'description' => 'Test finding',
            'due_date' => today()->addDays(14),
            'status' => 'open',
            'created_by' => $auditor->id,
        ]);

        $ca = \App\Models\CorrectiveAction::create([
            'finding_id' => $finding->id,
            'root_cause' => 'Root cause',
            'correction' => 'Correction',
            'corrective_action' => 'Action',
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => $client->id,
        ]);

        $initialNotificationsCount = \App\Models\PortalNotification::where('user_id', $tech->id)
            ->where('type', 'certificate_review')
            ->count();

        // 11. Setelah Corrective Action terakhir diterima: status menjadi certificate_review & notifikasi Tim Teknis dibuat
        $this->actingAs($auditor)
            ->post(route('audit.corrective-actions.review', $ca), [
                'status' => 'accepted',
                'notes' => 'CA diterima',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame('certificate_review', $app->refresh()->status);

        $newNotificationsCount = \App\Models\PortalNotification::where('user_id', $tech->id)
            ->where('type', 'certificate_review')
            ->count();
        $this->assertSame($initialNotificationsCount + 1, $newNotificationsCount);

        $notif = \App\Models\PortalNotification::where('user_id', $tech->id)
            ->where('type', 'certificate_review')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('Audit Selesai', $notif->title);
        $this->assertStringContainsString($app->order_number, $notif->message);
        $this->assertSame(route('technical.show', $app), $notif->action_url);

        // Uji bahwa panggilan review tidak menduplikasi notifikasi ketika status aplikasi sudah bukan corrective_action
        $this->actingAs($auditor)
            ->post(route('audit.corrective-actions.review', $ca), [
                'status' => 'accepted',
                'notes' => 'CA diterima kembali',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $finalNotificationsCount = \App\Models\PortalNotification::where('user_id', $tech->id)
            ->where('type', 'certificate_review')
            ->count();
        $this->assertSame($initialNotificationsCount + 1, $finalNotificationsCount);
    }
}
