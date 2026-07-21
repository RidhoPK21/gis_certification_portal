<?php

namespace Tests\Feature;

use App\Models\CertificateFinal;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificateLinkService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateTest extends TestCase
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

    private function application(User $client, string $status = 'certificate_review'): CertificationApplication
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
            'order_number' => 'CERT-' . Str::random(4),
            'order_date' => today(),
        ]);
    }

    public function test_non_technical_ditolak(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)
            ->get(route('technical.index'))
            ->assertForbidden();
    }

    public function test_teknis_upload_draft_dan_buat_link_preview(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $client = $this->user('client');
        $app = $this->application($client, 'certificate_review');

        $this->actingAs($tech)
            ->post(route('technical.draft.upload', $app), [
                'draft' => UploadedFile::fake()->create('draft.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $draft = $app->certificateDrafts()->firstOrFail();
        $this->assertDatabaseHas('certificate_drafts', ['application_id' => $app->id, 'version' => 1]);

        $this->actingAs($tech)
            ->post(route('technical.draft.link', $draft))
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_share_links', ['application_id' => $app->id, 'link_type' => 'draft']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'draft_available']);
    }

    public function test_teknis_upload_final_ubah_status_dan_selesai(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'certificate_review');

        $this->actingAs($tech)
            ->post(route('technical.final.upload', $app), [
                'certificate' => UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
                'certificate_number' => 'GIS-CERT-001',
                'issued_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('final_certificate', $app->status);
        $this->assertDatabaseHas('certificate_finals', ['application_id' => $app->id, 'certificate_number' => 'GIS-CERT-001']);

        $this->actingAs($tech)
            ->post(route('technical.complete', $app), [
                'notes' => 'Selesai.',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame('completed', $app->refresh()->status);
    }

    public function test_akses_publik_sertifikat_final_butuh_password_benar(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'final_certificate');

        Storage::disk('private')->put('applications/'.$app->id.'/certificates/final.pdf', '%PDF-1.4 test');
        $final = CertificateFinal::create([
            'application_id' => $app->id,
            'certificate_number' => 'GIS-CERT-XYZ',
            'original_name' => 'final.pdf',
            'file_path' => 'applications/'.$app->id.'/certificates/final.pdf',
            'checksum_sha256' => str_repeat('a', 64),
            'issued_date' => today(),
            'status' => 'released',
        ]);

        $result = app(CertificateLinkService::class)->create($app, 'final', $final->id, $tech->id);
        $token = $result['token'];
        $password = $result['password'];

        // Halaman akses publik terbuka tanpa login.
        $this->get(route('certificate.final.access', $token))->assertOk();

        // Password salah ditolak.
        $this->post(route('certificate.final.download', $token), ['password' => 'SALAH'])
            ->assertSessionHasErrors('password');

        // Password benar → berhasil (unduhan tercatat).
        $this->post(route('certificate.final.download', $token), ['password' => $password])
            ->assertOk();
        $this->assertDatabaseHas('certificate_download_logs', ['certificate_final_id' => $final->id]);
    }

    public function test_link_kedaluwarsa_tidak_bisa_diakses(): void
    {
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'final_certificate');
        $final = CertificateFinal::create([
            'application_id' => $app->id, 'certificate_number' => 'GIS-EXP-001', 'original_name' => 'f.pdf',
            'file_path' => 'x/f.pdf', 'checksum_sha256' => str_repeat('a', 64), 'issued_date' => today(), 'status' => 'released',
        ]);
        $result = app(CertificateLinkService::class)->create($app, 'final', $final->id, $tech->id, now()->subDay());

        $this->get(route('certificate.final.access', $result['token']))->assertStatus(410);
    }
}
