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

class TechnicalReviewTest extends TestCase
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
            'order_number' => 'TEK-001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function saveAdminReview(CertificationApplication $app, User $admin): void
    {
        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin',
        ])->assertRedirect();
    }

    public function test_admin_meneruskan_ke_teknis_mengubah_status_dan_memberi_notifikasi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->saveAdminReview($app, $admin);

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();

        $this->assertSame('technical_review', $app->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $tech->id, 'type' => 'technical_review_pending']);
    }

    public function test_meneruskan_ke_teknis_gagal_tanpa_kajian_administrasi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertStatus(422);
        $this->assertSame('admin_review', $app->refresh()->status);
    }

    public function test_tim_teknis_mengisi_dan_menyelesaikan_tinjauan(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->saveAdminReview($app, $admin);
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            'items' => [
                ['type' => 'checklist', 'code' => 'audit_mandays', 'label' => 'Mandays audit', 'status' => 'sufficient', 'notes' => 'ok'],
            ],
        ])->assertRedirect();

        $this->actingAs($tech)->post(route('technical.reviews.complete', $app))->assertRedirect();

        $app->refresh();
        $this->assertSame('admin_review', $app->status);
        $this->assertDatabaseHas('application_reviews', [
            'application_id' => $app->id,
            'review_type' => 'technical',
            'reviewed_by' => $tech->id,
        ]);
        $review = $app->reviews()->where('review_type', 'technical')->first();
        $this->assertNotNull($review->completed_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'technical_review_completed']);
    }

    public function test_approve_terblokir_sebelum_tinjauan_teknis_selesai(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));
        $this->saveAdminReview($app, $admin);

        $this->actingAs($admin)->post(route('internal.applications.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->assertSame('admin_review', $app->refresh()->status);
    }

    public function test_admin_tidak_bisa_mengisi_review_teknis_lewat_rute_admin(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        $this->actingAs($admin)
            ->from(route('internal.applications.show', $app))
            ->post(route('internal.applications.review', $app), [
                'review_type' => 'technical',
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Admin',
            ])
            ->assertSessionHasErrors('review_type');

        $this->assertDatabaseMissing('application_reviews', [
            'application_id' => $app->id,
            'review_type' => 'technical',
        ]);
    }

    public function test_non_teknis_ditolak_di_halaman_tinjauan_teknis(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');

        $this->actingAs($finance)->get(route('technical.reviews.index'))->assertForbidden();
    }
}
