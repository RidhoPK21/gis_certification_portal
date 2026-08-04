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

        // Upload final membuat rencana surveillance (Fase 8), sehingga penyelesaian
        // otomatis mengaktifkan status surveillance.
        $this->assertSame('surveillance', $app->refresh()->status);
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

    /**
     * Menyiapkan sertifikat final + link aman, mengembalikan URL aksesnya.
     */
    private function releaseFinal(CertificationApplication $app, User $tech): string
    {
        Storage::disk('private')->put('applications/'.$app->id.'/certificates/final.pdf', '%PDF-1.4 test');
        $final = CertificateFinal::create([
            'application_id' => $app->id,
            'certificate_number' => 'GIS-'.Str::random(6),
            'original_name' => 'final.pdf',
            'file_path' => 'applications/'.$app->id.'/certificates/final.pdf',
            'checksum_sha256' => str_repeat('a', 64),
            'issued_date' => today(),
            'status' => 'released',
        ]);

        $this->actingAs($tech)->post(route('technical.final.link', $final))->assertRedirect();

        return \App\Models\PortalNotification::where('type', 'final_available')
            ->where('user_id', $app->client_id)
            ->latest()
            ->value('action_url');
    }

    public function test_klien_melihat_link_sertifikat_di_halaman_permohonan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $client = $this->user('client');
        $app = $this->application($client, 'final_certificate');

        $url = $this->releaseFinal($app, $tech);
        $this->assertNotNull($url);

        $this->actingAs($client)
            ->get(route('client.applications.show', $app))
            ->assertOk()
            ->assertSee($url)
            ->assertSee('Buka Halaman Unduh Sertifikat');
    }

    public function test_link_sertifikat_tidak_bocor_ke_klien_lain(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $clientA = $this->user('client');
        $clientB = $this->user('client');
        $appA = $this->application($clientA, 'final_certificate');
        $appB = $this->application($clientB, 'final_certificate');

        $urlA = $this->releaseFinal($appA, $tech);
        $this->releaseFinal($appB, $tech);

        $this->actingAs($clientB)
            ->get(route('client.applications.show', $appB))
            ->assertOk()
            ->assertDontSee($urlA);
    }

    public function test_link_yang_dicabut_tidak_ditawarkan_ke_klien(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $client = $this->user('client');
        $app = $this->application($client, 'final_certificate');

        $url = $this->releaseFinal($app, $tech);
        $link = $app->certificateShareLinks()->where('link_type', 'final')->firstOrFail();
        $this->actingAs($tech)->post(route('technical.link.revoke', $link))->assertRedirect();

        $this->actingAs($client)
            ->get(route('client.applications.show', $app))
            ->assertOk()
            ->assertDontSee($url)
            ->assertSee('kedaluwarsa atau dinonaktifkan', false);
    }

    public function test_password_dan_link_ditampilkan_setelah_generate(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'final_certificate');

        Storage::disk('private')->put('applications/'.$app->id.'/certificates/final.pdf', '%PDF-1.4 test');
        $final = CertificateFinal::create([
            'application_id' => $app->id, 'certificate_number' => 'GIS-PWD-001', 'original_name' => 'final.pdf',
            'file_path' => 'applications/'.$app->id.'/certificates/final.pdf',
            'checksum_sha256' => str_repeat('a', 64), 'issued_date' => today(), 'status' => 'released',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.final.link', $final))
            ->assertSessionHas('generated_link.password');

        // from() wajib: createFinalLink memakai back(), tanpa referer redirect
        // jatuh ke dashboard dan banner tidak ikut ter-render.
        $this->actingAs($tech)
            ->from(route('technical.show', $app))
            ->followingRedirects()
            ->post(route('technical.final.link', $final))
            ->assertOk()
            ->assertSee('id="generated-link"', false)
            ->assertSee('Salin password');
    }

    public function test_qr_verifikasi_muncul_di_banner_dan_tidak_memuat_token(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'final_certificate');

        Storage::disk('private')->put('applications/'.$app->id.'/certificates/final.pdf', '%PDF-1.4 test');
        $final = CertificateFinal::create([
            'application_id' => $app->id, 'certificate_number' => 'QR/LSSM-GIS/VIII/2026', 'original_name' => 'final.pdf',
            'file_path' => 'applications/'.$app->id.'/certificates/final.pdf',
            'checksum_sha256' => str_repeat('a', 64), 'issued_date' => today(), 'status' => 'released',
        ]);

        $response = $this->actingAs($tech)
            ->from(route('technical.show', $app))
            ->post(route('technical.final.link', $final));

        $generated = session('generated_link');
        $this->assertNotNull($generated['verify_qr'] ?? null);
        $this->assertStringStartsWith('<svg', $generated['verify_qr']);
        $this->assertStringContainsString('cek-status', $generated['verify_url']);

        /*
         * Pengaman utama: QR hanya boleh membawa nomor sertifikat ke halaman
         * verifikasi publik, tidak boleh membawa token akses berkas.
         */
        $token = Str::afterLast(parse_url($generated['url'], PHP_URL_PATH), '/');
        $this->assertNotEmpty($token);
        $this->assertStringNotContainsString($token, $generated['verify_url']);
        $this->assertStringNotContainsString($token, $generated['verify_qr']);

        $response->assertRedirect();
    }

    public function test_klien_melihat_qr_verifikasi_di_halaman_permohonan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $client = $this->user('client');
        $app = $this->application($client, 'final_certificate');

        CertificateFinal::create([
            'application_id' => $app->id, 'certificate_number' => 'QRC/LSSM-GIS/VIII/2026', 'original_name' => 'f.pdf',
            'file_path' => 'x/f.pdf', 'checksum_sha256' => str_repeat('a', 64), 'issued_date' => today(), 'status' => 'released',
        ]);

        $this->actingAs($client)
            ->get(route('client.applications.show', $app))
            ->assertOk()
            ->assertSee('QR verifikasi sertifikat')
            ->assertSee('<svg', false);
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
