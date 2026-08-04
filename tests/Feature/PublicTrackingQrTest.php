<?php

namespace Tests\Feature;

use App\Models\ApplicationStatusHistory;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicTrackingQrTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    private function client(): User
    {
        $user = User::create([
            'name' => 'Klien',
            'email' => 'klien'.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    /**
     * Permohonan yang sudah dikirim dan sudah melewati beberapa tahap,
     * lengkap dengan riwayat status sebagai sumber tanggal timeline.
     */
    private function application(string $orderNumber, string $status = 'stage_1_audit'): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->client()->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Uji QR',
            'contact_email' => 'a@b.c',
            'order_number' => $orderNumber,
            'submitted_at' => now()->subDays(30),
        ]);

        $steps = [
            ['submitted', now()->subDays(30)],
            ['admin_review', now()->subDays(28)],
            ['application_approved', now()->subDays(24)],
            ['invoice_process', now()->subDays(20)],
            ['payment_completed', now()->subDays(12)],
            ['stage_1_audit', now()->subDays(5)],
        ];

        foreach ($steps as [$to, $date]) {
            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'to_status' => $to,
                'action' => 'test',
                'action_date' => $date,
                'system_recorded_at' => $date,
            ]);
        }

        return $application;
    }

    public function test_halaman_login_menautkan_ke_cek_status(): void
    {
        $this->seedAll();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sudah ada permohonan? Coba cek di sini')
            ->assertSee(route('public.home'));
    }

    public function test_hasil_pelacakan_menampilkan_qr_dan_tombol_unduh(): void
    {
        $this->seedAll();
        $this->application('QR-TRACK-1');

        $this->post(route('public.track'), ['order_number' => 'QR-TRACK-1'])
            ->assertOk()
            ->assertSee('QR pelacakan permohonan')
            ->assertSee('Unduh QR')
            ->assertSee(route('public.qr', ['nomor' => 'QR-TRACK-1']))
            // QR di-render inline sebagai SVG, bukan gambar dari host lain.
            ->assertSee('<svg', false);
    }

    public function test_timeline_publik_menampilkan_tanggal_tiap_tahap(): void
    {
        $this->seedAll();
        $this->application('QR-TRACK-2');

        $response = $this->get(route('public.home', ['nomor' => 'QR-TRACK-2']));

        $response->assertOk()
            ->assertSee('Progres Permohonan')
            ->assertSee('Mulai '.now()->subDays(30)->format('d M Y, H:i'))
            ->assertSee('Mulai '.now()->subDays(5)->format('d M Y, H:i'));
    }

    public function test_tahap_berjalan_ditandai_sesuai_status(): void
    {
        $this->seedAll();
        $this->application('QR-TRACK-3', 'admin_review');

        // admin_review masih berada di rentang tahap "Permohonan".
        $this->get(route('public.home', ['nomor' => 'QR-TRACK-3']))
            ->assertOk()
            ->assertSee('track-step current', false);
    }

    public function test_unduh_qr_menghasilkan_berkas_gambar(): void
    {
        $this->seedAll();
        $this->application('QR-TRACK-4');

        $response = $this->get(route('public.qr', ['nomor' => 'QR-TRACK-4']));

        $response->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertDownload('QR-Permohonan-qr-track-4.png');

        // Signature PNG memastikan isinya benar-benar gambar, bukan halaman galat.
        $this->assertStringStartsWith("\x89PNG", (string) $response->getContent());
    }

    public function test_unduh_qr_menolak_nomor_yang_tidak_terdaftar(): void
    {
        $this->seedAll();

        $this->get(route('public.qr', ['nomor' => 'TIDAK/ADA/2026']))->assertNotFound();
        $this->get(route('public.qr'))->assertNotFound();
    }

    public function test_klien_melihat_qr_pelacakan_setelah_permohonan_punya_nomor(): void
    {
        $this->seedAll();
        $application = $this->application('QR-TRACK-5');

        $this->actingAs($application->client)
            ->get(route('client.applications.show', $application))
            ->assertOk()
            ->assertSee('QR Pelacakan Permohonan')
            ->assertSee('Unduh QR')
            ->assertSee(route('public.qr', ['nomor' => 'QR-TRACK-5']));
    }

    public function test_draft_tanpa_nomor_order_belum_menampilkan_qr(): void
    {
        $this->seedAll();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $client = $this->client();

        $draft = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'draft',
            'current_step' => 'draft',
            'company_name' => 'PT Draft',
            'contact_email' => 'a@b.c',
        ]);

        $this->actingAs($client)
            ->get(route('client.applications.show', $draft))
            ->assertOk()
            ->assertDontSee('QR Pelacakan Permohonan');
    }
}
