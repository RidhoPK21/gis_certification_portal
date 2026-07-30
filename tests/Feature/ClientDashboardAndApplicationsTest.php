<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\PortalNotification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardAndApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private function createClientUser(string $email = 'client.test@example.com', string $name = 'Klien Test'): User
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'RahasiaKuat123',
            'is_active' => true,
            'company_name' => 'PT Test',
            'phone' => '08111222333',
        ]);

        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    private function createCertApplication(User $client, CertificationScheme $scheme, string $status = 'draft', ?string $orderNumber = null): CertificationApplication
    {
        $app = CertificationApplication::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'status' => $status,
            'order_number' => $orderNumber,
            'company_name' => $client->company_name,
            'applicant_name' => $client->name,
            'contact_email' => $client->email,
            'contact_phone' => $client->phone,
            'form_version' => $scheme->form_version,
        ]);

        return $app;
    }

    public function test_klien_melihat_seluruh_permohonan_termasuk_draft_tanpa_filter(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $draftApp = $this->createCertApplication($client, $scheme, 'draft', null);
        $submittedApp = $this->createCertApplication($client, $scheme, 'submitted', 'ORD-SUB-101');

        $response = $this->actingAs($client)->get(route('client.applications.index'));

        $response->assertOk()
            ->assertSee('Draft #' . $draftApp->id)
            ->assertSee('ORD-SUB-101');
    }

    public function test_klien_tidak_dapat_melihat_permohonan_klien_lain(): void
    {
        $clientA = $this->createClientUser('a@example.com', 'Klien A');
        $clientB = $this->createClientUser('b@example.com', 'Klien B');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($clientA, $scheme, 'submitted', 'ORD-KLIEN-A-999');
        $this->createCertApplication($clientB, $scheme, 'submitted', 'ORD-KLIEN-B-888');

        $this->actingAs($clientA)
            ->get(route('client.applications.index'))
            ->assertOk()
            ->assertSee('ORD-KLIEN-A-999')
            ->assertDontSee('ORD-KLIEN-B-888');
    }

    public function test_parameter_filter_kosong_tidak_menghasilkan_daftar_kosong(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($client, $scheme, 'submitted', 'ORD-KOSONG-777');

        $this->actingAs($client)
            ->get(route('client.applications.index', ['scheme_id' => '', 'q' => '', 'status' => '']))
            ->assertOk()
            ->assertSee('ORD-KOSONG-777');
    }

    public function test_filter_scheme_id_valid_hanya_menampilkan_skema_tersebut(): void
    {
        $client = $this->createClientUser();
        $schemes = CertificationScheme::orderBy('sort_order')->take(2)->get();
        $scheme1 = $schemes[0];
        $scheme2 = $schemes[1];

        $this->createCertApplication($client, $scheme1, 'submitted', 'ORD-SCHEME-ONE');
        $this->createCertApplication($client, $scheme2, 'submitted', 'ORD-SCHEME-TWO');

        $this->actingAs($client)
            ->get(route('client.applications.index', ['scheme_id' => $scheme1->id]))
            ->assertOk()
            ->assertSee('ORD-SCHEME-ONE')
            ->assertDontSee('ORD-SCHEME-TWO');
    }

    public function test_filter_scheme_id_tanpa_data_menghasilkan_empty_state_bukan_error(): void
    {
        $client = $this->createClientUser();
        $schemes = CertificationScheme::orderBy('sort_order')->take(2)->get();
        $scheme1 = $schemes[0];
        $scheme2 = $schemes[1];

        $this->createCertApplication($client, $scheme1, 'submitted', 'ORD-SCHEME-HAS-DATA');

        $response = $this->actingAs($client)
            ->get(route('client.applications.index', ['scheme_id' => $scheme2->id]));

        $response->assertOk()
            ->assertSee('Tidak ada permohonan yang sesuai dengan filter.')
            ->assertDontSee('ORD-SCHEME-HAS-DATA');
    }

    public function test_kombinasi_search_status_dan_scheme_id(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $app1 = $this->createCertApplication($client, $scheme, 'submitted', 'ORD-KOMBI-MATCH');
        $app2 = $this->createCertApplication($client, $scheme, 'admin_review', 'ORD-KOMBI-WRONG-STATUS');

        $this->actingAs($client)
            ->get(route('client.applications.index', [
                'q' => 'KOMBI-MATCH',
                'status' => 'submitted',
                'scheme_id' => $scheme->id,
            ]))
            ->assertOk()
            ->assertSee('ORD-KOMBI-MATCH')
            ->assertDontSee('ORD-KOMBI-WRONG-STATUS');
    }

    public function test_search_tidak_dapat_melewati_pembatasan_client_id(): void
    {
        $clientA = $this->createClientUser('a2@example.com', 'Klien A2');
        $clientB = $this->createClientUser('b2@example.com', 'Klien B2');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($clientA, $scheme, 'submitted', 'ORD-A-SHARED');
        $this->createCertApplication($clientB, $scheme, 'submitted', 'ORD-B-SHARED');

        $this->actingAs($clientA)
            ->get(route('client.applications.index', ['q' => 'SHARED']))
            ->assertOk()
            ->assertSee('ORD-A-SHARED')
            ->assertDontSee('ORD-B-SHARED');
    }

    public function test_tombol_lihat_semua_pada_dashboard_tanpa_parameter_kosong(): void
    {
        $client = $this->createClientUser();

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('href="' . route('client.applications.index') . '"', false);
    }

    public function test_dashboard_klien_tidak_merender_card_notifikasi_tetapi_merender_perlu_tindakan(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        PortalNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $client->id,
            'type' => 'info',
            'title' => 'Notifikasi Uji Header',
            'message' => 'Pesan untuk uji header',
            'action_url' => '#',
        ]);

        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-PERLU-REVISI');

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Perlu Tindakan')
            ->assertSee('ORD-PERLU-REVISI')
            ->assertDontSee('<h2 class="mb-0">Notifikasi</h2>', false);
    }

    public function test_header_notifikasi_tetap_tersedia(): void
    {
        $client = $this->createClientUser();

        PortalNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $client->id,
            'type' => 'info',
            'title' => 'Judul Notif Header Uji',
            'message' => 'Isi notifikasi header',
            'action_url' => '#',
        ]);

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Judul Notif Header Uji')
            ->assertSee('notif-dropdown');
    }

    public function test_panel_perlu_tindakan_hanya_menampilkan_data_milik_klien(): void
    {
        $clientA = $this->createClientUser('a3@example.com', 'Klien A3');
        $clientB = $this->createClientUser('b3@example.com', 'Klien B3');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($clientA, $scheme, 'revision_requested', 'ACTION-KLIEN-A-111');
        $this->createCertApplication($clientB, $scheme, 'revision_requested', 'ACTION-KLIEN-B-222');

        $this->actingAs($clientA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ACTION-KLIEN-A-111')
            ->assertDontSee('ACTION-KLIEN-B-222');
    }

    public function test_kartu_statistik_perlu_tindakan_sinkron_dengan_panel_dan_tidak_menghitung_yang_selesai(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-REV-100');
        $this->createCertApplication($client, $scheme, 'invoice_process', 'ORD-INV-200');
        $this->createCertApplication($client, $scheme, 'completed', 'ORD-COMPLETED-300');

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('ORD-REV-100')
            ->assertSee('ORD-INV-200')
            ->assertSee('<div class="stat-card"><small>Perlu Tindakan</small><strong>2</strong></div>', false);
    }

    public function test_label_tombol_action_sesuai_status(): void
    {
        $client1 = $this->createClientUser('client1@test.com', 'Client 1');
        $client2 = $this->createClientUser('client2@test.com', 'Client 2');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($client1, $scheme, 'draft', null);
        $this->createCertApplication($client1, $scheme, 'invoice_process', 'ORD-INV-BTN');
        $this->createCertApplication($client1, $scheme, 'certificate_review', 'ORD-CERT-BTN');

        $response1 = $this->actingAs($client1)->get(route('dashboard'));
        $response1->assertOk()
            ->assertSee('Lanjutkan Draft')
            ->assertDontSee('Perbaiki Data')
            ->assertSee('Lihat Pembayaran')
            ->assertSee('Tinjau Draft');

        $this->createCertApplication($client2, $scheme, 'revision_requested', 'ORD-REV-BTN');
        $this->createCertApplication($client2, $scheme, 'corrective_action', 'ORD-CORR-BTN');
        $this->createCertApplication($client2, $scheme, 'corrective_revision', 'ORD-CORREV-BTN');

        $response2 = $this->actingAs($client2)->get(route('dashboard'));
        $response2->assertOk()
            ->assertSee('Perbaiki Permohonan')
            ->assertSee('Isi Tindakan Koreksi')
            ->assertSee('Perbaiki Tindakan Koreksi');
    }

    public function test_ringkasan_draft_tampil_jika_terdapat_beberapa_draft_dan_tidak_memakai_perbaiki_data(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($client, $scheme, 'draft', null);
        $this->createCertApplication($client, $scheme, 'draft', null);
        $this->createCertApplication($client, $scheme, 'draft', null);

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('3 draft permohonan belum selesai')
            ->assertSee('Lihat Draft')
            ->assertDontSee('Perbaiki Data');
    }

    public function test_urutan_dan_pembatasan_maksimal_3_item_individual_pada_panel(): void
    {
        $client = $this->createClientUser();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-REV-1');
        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-REV-2');
        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-REV-3');
        $this->createCertApplication($client, $scheme, 'revision_requested', 'ORD-REV-4');

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('<div class="stat-card"><small>Perlu Tindakan</small><strong>4</strong></div>', false)
            ->assertSee('1 permohonan lain memerlukan perhatian')
            ->assertSee('Lihat Semua Tindakan');
    }

    public function test_empty_state_perlu_tindakan(): void
    {
        $client = $this->createClientUser();

        $response = $this->actingAs($client)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('<div class="stat-card"><small>Perlu Tindakan</small><strong>0</strong></div>', false)
            ->assertSee('Tidak ada tindakan yang perlu Anda selesaikan saat ini.')
            ->assertSee('Perkembangan terbaru tetap dapat dipantau melalui notifikasi di bagian atas.');
    }
}
