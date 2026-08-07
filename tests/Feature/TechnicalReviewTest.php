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

    /**
     * Membawa permohonan sampai tahap technical_review agar test tidak
     * mengulang rangkaian yang sama.
     */
    private function forwardToTechnical(CertificationApplication $app, User $admin): void
    {
        $this->saveAdminReview($app, $admin);
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();
    }

    public function test_form_teknis_menampilkan_dokumen_teknis_bukan_administrasi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->forwardToTechnical($app, $admin);

        $html = $this->actingAs($tech)
            ->get(route('technical.reviews.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Kajian Dokumen Teknis', $html);
        $this->assertStringContainsString('value="system_manual"', $html);
        $this->assertStringNotContainsString('value="nib"', $html);
    }

    /**
     * Pasangan dari test pemisahan di ReviewAdminTest: dokumen yang dikeluarkan
     * dari form admin harus benar-benar muncul di form Tim Teknis untuk setiap
     * skema, supaya tidak ada dokumen yang kehilangan tempat penilaian.
     */
    public function test_semua_dokumen_teknis_punya_tempat_di_form_tim_teknis(): void
    {
        $this->seedAll();
        $tech = $this->user('technical');
        $client = $this->user('client');

        foreach (CertificationScheme::with('requiredDocuments')->orderBy('sort_order')->get() as $scheme) {
            $app = CertificationApplication::create([
                'uuid' => (string) Str::uuid(),
                'client_id' => $client->id,
                'certification_scheme_id' => $scheme->id,
                'form_version' => $scheme->form_version,
                'status' => 'technical_review',
                'current_step' => 'technical_review',
                'company_name' => 'PT Uji '.$scheme->code,
                'contact_email' => 'kontak@uji.test',
                'order_number' => 'TEK-'.$scheme->code,
                'order_date' => today(),
                'submitted_at' => now(),
            ]);

            /*
             * Dua formulir tidak membagi penilaian per dokumen antara Admin dan
             * Tim Teknis, jadi pemeriksaan di bawah tidak berlaku:
             *
             * - ISPO (FrO.7204) membaginya per bagian formulir, bukan per
             *   dokumen, dan barisnya punya kode sendiri.
             * - LSPro (Fr.7201) hanya dikaji Admin; Tim Teknis memverifikasi
             *   pekerjaan Admin secara menyeluruh lewat tampilan baca-saja,
             *   sehingga form-nya memang tidak memuat input per dokumen.
             */
            if (in_array($scheme->review_template, ['ispo', 'sni'], true)) {
                continue;
            }

            /*
             * Acuannya baris formulir tinjauan. Dokumen yang muncul di kedua
             * tabel memang tampil di dua form (Admin menilai kelengkapan, Tim
             * Teknis menilai substansi), jadi yang tidak boleh bocor hanyalah
             * baris yang khusus administrasi.
             */
            $reviews = app(\App\Services\ReviewService::class);
            $technicalCodes = $reviews->formRows($app, 'technical')->pluck('code');
            $adminOnlyCodes = $reviews->formRows($app, 'administration')->pluck('code')->diff($technicalCodes);

            $html = $this->actingAs($tech)
                ->get(route('technical.reviews.show', $app))
                ->assertOk()
                ->getContent();

            foreach ($technicalCodes as $code) {
                $this->assertStringContainsString('value="'.$code.'"', $html, $scheme->code.': dokumen teknis '.$code.' tidak ada di form Tim Teknis.');
            }
            foreach ($adminOnlyCodes as $code) {
                $this->assertStringNotContainsString('value="'.$code.'"', $html, $scheme->code.': dokumen administrasi '.$code.' bocor ke form Tim Teknis.');
            }
        }
    }

    public function test_tim_teknis_menyimpan_aspek_teknis_ke_application_values(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->forwardToTechnical($app, $admin);

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            /*
             * Tim auditor tidak lagi diketik di sini — namanya diambil dari
             * penugasan auditor — jadi yang diuji tinggal aspek teks lainnya.
             */
            'aspects' => ['audit_mandays' => '5', 'required_auditor_competence' => 'Ruang lingkup beton'],
        ])->assertRedirect();

        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id, 'field_code' => 'audit_mandays', 'value_text' => '5',
        ]);
        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id, 'field_code' => 'required_auditor_competence', 'value_text' => 'Ruang lingkup beton',
        ]);

        // Simpan ulang harus meng-update baris yang sama, bukan menambah.
        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            'aspects' => ['audit_mandays' => '8'],
        ])->assertRedirect();

        $this->assertSame(1, $app->values()->where('field_code', 'audit_mandays')->count());
        $this->assertSame('8', $app->values()->where('field_code', 'audit_mandays')->value('value_text'));
    }

    public function test_tim_teknis_menyimpan_kajian_dokumen_teknis(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->forwardToTechnical($app, $admin);

        \App\Models\ApplicationDocument::create([
            'application_id' => $app->id,
            'document_code' => 'system_manual',
            'document_name' => 'Manual Sistem',
        ]);

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            'items' => [
                ['type' => 'document', 'code' => 'system_manual', 'label' => 'Manual Sistem', 'status' => 'meets', 'notes' => 'lengkap'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('review_form_items', ['item_code' => 'system_manual', 'review_status' => 'meets']);
        $this->assertDatabaseHas('application_documents', [
            'application_id' => $app->id, 'document_code' => 'system_manual', 'review_status' => 'meets',
        ]);
    }

    public function test_tim_teknis_tidak_dapat_mengubah_dokumen_administrasi(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->forwardToTechnical($app, $admin);

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            'items' => [
                ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('review_form_items', ['item_code' => 'nib']);
    }

    public function test_aspek_teknis_ikut_pada_snapshot_pdf_tinjauan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $admin = $this->user('admin_application');
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));
        $this->forwardToTechnical($app, $admin);

        $this->actingAs($tech)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Peninjau Teknis',
            'aspects' => ['audit_mandays' => '5'],
        ])->assertRedirect();
        $this->actingAs($tech)->post(route('technical.reviews.complete', $app))->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $pdf = \App\Models\GeneratedPdf::latest('id')->firstOrFail();
        $this->assertSame('5', $pdf->source_snapshot['values']['audit_mandays'] ?? null);
    }

    public function test_tim_teknis_dapat_mengunduh_dokumen_permohonan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->applicationInReview($this->user('client'));

        $document = \App\Models\ApplicationDocument::create([
            'application_id' => $app->id,
            'document_code' => 'system_manual',
            'document_name' => 'Manual Sistem',
        ]);
        Storage::disk('private')->put('applications/'.$app->id.'/manual.pdf', 'isi');
        \App\Models\ApplicationDocumentVersion::create([
            'application_document_id' => $document->id,
            'version' => 1,
            'original_name' => 'manual.pdf',
            'stored_name' => 'manual.pdf',
            'file_path' => 'applications/'.$app->id.'/manual.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 3,
            'checksum_sha256' => hash('sha256', 'isi'),
            'is_current' => true,
            'uploaded_by' => $app->client_id,
        ]);

        $this->actingAs($tech)
            ->get(route('secure-files.application-document', $document))
            ->assertOk();
    }

    public function test_non_teknis_ditolak_di_halaman_tinjauan_teknis(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');

        $this->actingAs($finance)->get(route('technical.reviews.index'))->assertForbidden();
    }
}
