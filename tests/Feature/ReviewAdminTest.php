<?php

namespace Tests\Feature;

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

class ReviewAdminTest extends TestCase
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

    private function applicationInReview(User $client): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'TEST-001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_admin_dapat_melihat_daftar_review(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $this->applicationInReview($client);

        $this->actingAs($admin)
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->assertSee('Review Permohonan');
    }

    public function test_klien_ditolak_di_modul_review(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)
            ->get(route('internal.applications.index'))
            ->assertForbidden();
    }

    public function test_admin_dapat_membuka_detail_review(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        $this->actingAs($admin)
            ->get(route('internal.applications.show', $app))
            ->assertOk()
            ->assertSee('Kajian Dokumen Administrasi');
    }

    public function test_admin_dapat_meminta_revisi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $app = $this->applicationInReview($client);

        $this->actingAs($admin)
            ->post(route('internal.applications.revision', $app), [
                'targets' => [
                    ['type' => 'field', 'code' => 'company_name', 'label' => 'Nama perusahaan', 'note' => 'Lengkapi nama resmi.'],
                ],
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('revision_requested', $app->status);
        $this->assertDatabaseHas('application_revision_items', ['application_id' => $app->id, 'target_code' => 'company_name', 'status' => 'open']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'revision_requested']);
    }

    public function test_admin_dapat_menyetujui_dan_generate_pdf(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $app = $this->applicationInReview($client);

        $this->actingAs($admin)
            ->post(route('internal.applications.approve', $app), [
                'action_date' => now()->format('Y-m-d'),
                'notes' => 'Lengkap.',
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('invoice_process', $app->status);
        $this->assertNotNull($app->approved_at);
        $this->assertDatabaseHas('generated_pdfs', ['application_id' => $app->id, 'document_type' => 'application_review']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'application_approved']);
    }

    public function test_admin_dapat_menolak(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');
        $app = $this->applicationInReview($client);

        $this->actingAs($admin)
            ->post(route('internal.applications.reject', $app), [
                'action_date' => now()->format('Y-m-d'),
                'reason' => 'Dokumen tidak memenuhi.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $app->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'application_rejected']);
    }

    public function test_admin_dapat_menyimpan_kajian_dokumen(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        $this->actingAs($admin)
            ->post(route('internal.applications.review', $app), [
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Reviewer',
                'items' => [
                    ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient', 'notes' => 'ok'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'administration']);
        $this->assertDatabaseHas('review_form_items', ['item_code' => 'nib', 'review_status' => 'sufficient']);
    }

    public function test_menyetujui_menandai_kajian_administrasi_dan_teknis_diterima(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        foreach (['administration', 'technical'] as $type) {
            $this->actingAs($admin)->post(route('internal.applications.review', $app), [
                'review_type' => $type,
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Reviewer',
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('internal.applications.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Lengkap.',
        ])->assertRedirect();

        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'administration', 'status' => 'approved']);
        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'technical', 'status' => 'approved']);
    }
}
