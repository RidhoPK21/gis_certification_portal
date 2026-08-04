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

    /**
     * Menjalankan alur tinjauan teknis oleh Tim Teknis: admin menyimpan kajian
     * administrasi, meneruskan ke teknis, lalu user teknis mengisi & menyelesaikan.
     * Setelah ini status kembali ke admin_review dan siap disetujui admin.
     */
    private function completeTechnicalReview(CertificationApplication $app, User $admin): User
    {
        $tech = $this->user('technical');

        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();
        $this->assertSame('technical_review', $app->refresh()->status);

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Teknis',
            'items' => [
                ['type' => 'checklist', 'code' => 'audit_mandays', 'label' => 'Mandays audit', 'status' => 'sufficient', 'notes' => 'ok'],
            ],
        ])->assertRedirect();

        $this->actingAs($tech)->post(route('technical.reviews.complete', $app))->assertRedirect();
        $this->assertSame('admin_review', $app->refresh()->status);

        return $tech;
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
        $this->completeTechnicalReview($app, $admin);

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

    public function test_form_kajian_admin_hanya_menampilkan_dokumen_administrasi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));

        $html = $this->actingAs($admin)
            ->get(route('internal.applications.show', $app))
            ->assertOk()
            ->getContent();

        /*
         * Diiris per-section: nama dokumen teknis tetap muncul di tabel target
         * revisi (admin yang meminta revisi ke klien untuk semua dokumen),
         * jadi assertDontSee pada halaman penuh akan menyesatkan.
         */
        // betweenFirst, bukan between: between() memakai beforeLast() sehingga
        // irisannya menelan hampir seluruh halaman.
        $sectionKajian = Str::betweenFirst($html, 'id="dokumen"', '</section>');

        $this->assertStringContainsString('value="nib"', $sectionKajian);
        $this->assertStringNotContainsString('value="system_manual"', $sectionKajian);
        $this->assertStringContainsString('value="system_manual"', $html);
    }

    /**
     * Pemisahan administrasi/teknis harus berlaku untuk SELURUH skema, bukan
     * hanya skema pertama. Test ini menjaga agar penambahan skema baru lewat
     * Form Builder tidak diam-diam mengembalikan dokumen teknis ke form admin.
     */
    public function test_pemisahan_dokumen_berlaku_untuk_semua_skema(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $client = $this->user('client');

        $schemes = CertificationScheme::with('requiredDocuments')->orderBy('sort_order')->get();
        $this->assertGreaterThanOrEqual(10, $schemes->count(), 'Katalog skema tidak ter-seed penuh.');

        foreach ($schemes as $scheme) {
            $app = CertificationApplication::create([
                'uuid' => (string) Str::uuid(),
                'client_id' => $client->id,
                'certification_scheme_id' => $scheme->id,
                'form_version' => $scheme->form_version,
                'status' => 'admin_review',
                'current_step' => 'admin_review',
                'company_name' => 'PT Uji '.$scheme->code,
                'contact_email' => 'kontak@uji.test',
                'order_number' => 'ALL-'.$scheme->code,
                'order_date' => today(),
                'submitted_at' => now(),
            ]);

            $groups = $scheme->requiredDocuments->groupBy(
                fn ($doc) => ($doc->review_group ?? 'administration') === 'technical' ? 'technical' : 'administration'
            );
            $adminCodes = ($groups['administration'] ?? collect())->pluck('code');
            $technicalCodes = ($groups['technical'] ?? collect())->pluck('code');

            $this->assertTrue($adminCodes->isNotEmpty(), $scheme->code.': tidak punya dokumen administrasi.');
            $this->assertTrue($technicalCodes->isNotEmpty(), $scheme->code.': tidak punya dokumen teknis.');

            // 1. Form admin hanya memuat dokumen administrasi.
            $html = $this->actingAs($admin)
                ->get(route('internal.applications.show', $app))
                ->assertOk()
                ->getContent();
            $sectionKajian = Str::betweenFirst($html, 'id="dokumen"', '</section>');

            foreach ($adminCodes as $code) {
                $this->assertStringContainsString('value="'.$code.'"', $sectionKajian, $scheme->code.': '.$code.' hilang dari form admin.');
            }
            foreach ($technicalCodes as $code) {
                $this->assertStringNotContainsString('value="'.$code.'"', $sectionKajian, $scheme->code.': dokumen teknis '.$code.' masih di form admin.');
            }

            // 2. Backend menolak walau payload dipaksa lewat rute admin.
            $this->actingAs($admin)
                ->post(route('internal.applications.review', $app), [
                    'review_type' => 'administration',
                    'action_date' => now()->format('Y-m-d'),
                    'signed_name' => 'Reviewer',
                    'items' => [
                        ['type' => 'document', 'code' => $technicalCodes->first(), 'label' => 'Dokumen Teknis', 'status' => 'sufficient'],
                    ],
                ])
                ->assertStatus(422);

            $this->assertDatabaseMissing('review_form_items', ['item_code' => $technicalCodes->first()]);
        }
    }

    public function test_admin_tidak_dapat_menyimpan_kajian_dokumen_teknis(): void
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
                    ['type' => 'document', 'code' => 'system_manual', 'label' => 'Manual Sistem', 'status' => 'sufficient'],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('review_form_items', ['item_code' => 'system_manual']);
    }

    public function test_menyetujui_menandai_kajian_administrasi_dan_teknis_diterima(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $app = $this->applicationInReview($this->user('client'));
        $this->completeTechnicalReview($app, $admin);

        $this->actingAs($admin)->post(route('internal.applications.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Lengkap.',
        ])->assertRedirect();

        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'administration', 'status' => 'approved']);
        $this->assertDatabaseHas('application_reviews', ['application_id' => $app->id, 'review_type' => 'technical', 'status' => 'approved']);
    }
}
