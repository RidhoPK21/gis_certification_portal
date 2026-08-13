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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pembagian skema antar tim internal.
 *
 * ISPO menjadi tanggung jawab Tim Admin Sustain dan Tim Teknis Sustain; skema
 * lain tetap milik Admin Permohonan dan Tim Teknis. Akun yang memegang dua role
 * melihat gabungannya.
 */
class SchemeOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    /**
     * @param  array<int, string>  $roleCodes
     */
    private function user(array $roleCodes): User
    {
        $user = User::create([
            'name' => implode('+', $roleCodes).' '.Str::random(3),
            'email' => Str::random(8).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::whereIn('code', $roleCodes)->pluck('id'));

        return $user;
    }

    private function order(string $schemeCode, string $status = 'admin_review'): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user(['client'])->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT '.$schemeCode,
            'contact_email' => 'kontak@uji.test',
            'order_number' => $schemeCode.'-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_admin_permohonan_tidak_melihat_ispo(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');

        $html = $this->actingAs($this->user(['admin_application']))
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($iso->order_number, $html);
        $this->assertStringNotContainsString($ispo->order_number, $html);
    }

    public function test_admin_sustain_hanya_melihat_ispo(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');

        $html = $this->actingAs($this->user(['admin_sustain']))
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ispo->order_number, $html);
        $this->assertStringNotContainsString($iso->order_number, $html);
    }

    public function test_membuka_order_milik_tim_lain_ditolak(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');

        // Menyaring daftar saja tidak cukup — URL langsung harus ikut tertutup.
        $this->actingAs($this->user(['admin_application']))
            ->get(route('internal.applications.show', $ispo))
            ->assertForbidden();

        $this->actingAs($this->user(['admin_sustain']))
            ->get(route('internal.applications.show', $iso))
            ->assertForbidden();
    }

    public function test_menulis_ke_order_milik_tim_lain_ditolak(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');

        $this->actingAs($this->user(['admin_application']))
            ->post(route('internal.applications.review', $ispo), [
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $ispo->reviews()->count());
    }

    public function test_akun_dua_role_melihat_dan_membuka_keduanya(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');
        $keduanya = $this->user(['admin_application', 'admin_sustain']);

        $html = $this->actingAs($keduanya)
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ispo->order_number, $html);
        $this->assertStringContainsString($iso->order_number, $html);

        $this->actingAs($keduanya)->get(route('internal.applications.show', $ispo))->assertOk();
        $this->actingAs($keduanya)->get(route('internal.applications.show', $iso))->assertOk();
    }

    public function test_tim_teknis_dan_teknis_sustain_terpisah(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO', 'technical_review');
        $iso = $this->order('ISO9001', 'technical_review');

        $html = $this->actingAs($this->user(['technical']))
            ->get(route('technical.reviews.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($iso->order_number, $html);
        $this->assertStringNotContainsString($ispo->order_number, $html);

        $htmlSustain = $this->actingAs($this->user(['technical_sustain']))
            ->get(route('technical.reviews.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($ispo->order_number, $htmlSustain);
        $this->assertStringNotContainsString($iso->order_number, $htmlSustain);

        $this->actingAs($this->user(['technical']))
            ->get(route('technical.reviews.show', $ispo))
            ->assertForbidden();

        $this->actingAs($this->user(['technical_sustain']))
            ->get(route('technical.reviews.show', $ispo))
            ->assertOk();
    }

    public function test_halaman_surat_tugas_ikut_terbagi(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO', 'payment_completed');

        $this->actingAs($this->user(['technical']))
            ->get(route('technical.assignments.show', $ispo))
            ->assertForbidden();

        $this->actingAs($this->user(['technical_sustain']))
            ->get(route('technical.assignments.show', $ispo))
            ->assertOk();
    }

    public function test_superadmin_tetap_melihat_seluruhnya(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');
        $superadmin = $this->user(['superadmin']);

        $html = $this->actingAs($superadmin)
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ispo->order_number, $html);
        $this->assertStringContainsString($iso->order_number, $html);

        $this->actingAs($superadmin)->get(route('internal.applications.show', $ispo))->assertOk();
    }

    public function test_dashboard_tersaring_per_kepemilikan(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');
        $iso = $this->order('ISO9001');

        $html = $this->actingAs($this->user(['admin_sustain']))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ispo->order_number, $html);
        $this->assertStringNotContainsString($iso->order_number, $html,
            'Dashboard Sustain seharusnya tidak memuat order non-ISPO.');
    }

    /**
     * Finance dan Auditor tidak memegang peran pemilik skema sama sekali.
     * Menyaring mereka per skema — alih-alih membiarkannya lewat — pernah
     * mengosongkan seluruh antreannya; cakupan mereka dibatasi status order dan
     * penugasan, bukan pembagian ini.
     */
    public function test_peran_bersama_tidak_ikut_tersaring(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO', 'invoice_process');
        $iso = $this->order('ISO9001', 'invoice_process');

        $html = $this->actingAs($this->user(['finance']))
            ->get(route('finance.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($ispo->order_number, $html);
        $this->assertStringContainsString($iso->order_number, $html);

        $ownership = app(\App\Services\SchemeOwnershipService::class);
        $this->assertNull($ownership->templatesOwnedBy($this->user(['auditor'])));
        $this->assertNull($ownership->templatesOwnedBy($this->user(['finance'])));
    }

    public function test_notifikasi_mendarat_di_tim_pemilik_skema(): void
    {
        $this->seedAll();
        $adminLama = $this->user(['admin_application']);
        $adminSustain = $this->user(['admin_sustain']);
        $teknisLama = $this->user(['technical']);
        $teknisSustain = $this->user(['technical_sustain']);

        $notifications = app(\App\Services\PortalNotificationService::class);

        $notifications->sendToSchemeOwner($this->order('ISPO'), 'admin', 'uji_ispo_admin', 'ISPO', 'pesan');
        $notifications->sendToSchemeOwner($this->order('ISO9001'), 'admin', 'uji_iso_admin', 'ISO', 'pesan');
        $notifications->sendToSchemeOwner($this->order('ISPO'), 'technical', 'uji_ispo_teknis', 'ISPO', 'pesan');
        $notifications->sendToSchemeOwner($this->order('ISO9001'), 'technical', 'uji_iso_teknis', 'ISO', 'pesan');

        $this->assertDatabaseHas('notifications', ['user_id' => $adminSustain->id, 'type' => 'uji_ispo_admin']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $adminLama->id, 'type' => 'uji_ispo_admin']);

        $this->assertDatabaseHas('notifications', ['user_id' => $adminLama->id, 'type' => 'uji_iso_admin']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $adminSustain->id, 'type' => 'uji_iso_admin']);

        $this->assertDatabaseHas('notifications', ['user_id' => $teknisSustain->id, 'type' => 'uji_ispo_teknis']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $teknisLama->id, 'type' => 'uji_ispo_teknis']);

        $this->assertDatabaseHas('notifications', ['user_id' => $teknisLama->id, 'type' => 'uji_iso_teknis']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $teknisSustain->id, 'type' => 'uji_iso_teknis']);
    }

    /**
     * Akun nonaktif ikut tersaring — pembungkus baru ini sekaligus memperbaiki
     * kelalaian lama sendToRole yang mengabaikan is_active.
     */
    public function test_notifikasi_melewati_akun_nonaktif(): void
    {
        $this->seedAll();
        $aktif = $this->user(['admin_sustain']);
        $nonaktif = $this->user(['admin_sustain']);
        $nonaktif->update(['is_active' => false]);

        app(\App\Services\PortalNotificationService::class)
            ->sendToSchemeOwner($this->order('ISPO'), 'admin', 'uji_nonaktif', 'ISPO', 'pesan');

        $this->assertDatabaseHas('notifications', ['user_id' => $aktif->id, 'type' => 'uji_nonaktif']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $nonaktif->id, 'type' => 'uji_nonaktif']);
    }

    /**
     * Rantai teknis ISPO — sampai sertifikat dan surveillance — seluruhnya milik
     * Teknis Sustain, bukan hanya tahap tinjauan.
     */
    public function test_rantai_sertifikat_ispo_milik_teknis_sustain(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO', 'certificate_review');
        $teknis = $this->user(['technical']);
        $teknisSustain = $this->user(['technical_sustain']);

        foreach (['technical.index', 'technical.reviews.index', 'technical.assignments.index'] as $route) {
            $html = $this->actingAs($teknisSustain)->get(route($route))->assertOk()->getContent();
            $this->assertStringNotContainsString('Tidak ada akses', $html);
        }

        $this->actingAs($teknis)->get(route('technical.show', $ispo))->assertForbidden();
        $this->actingAs($teknisSustain)->get(route('technical.show', $ispo))->assertOk();

        // Antrean sertifikat Tim Teknis lama tidak lagi memuat ISPO.
        $html = $this->actingAs($teknis)->get(route('technical.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString($ispo->order_number, $html);
    }

    /**
     * Daftar peran yang tersebar mudah terlewat, dan kegagalannya sunyi: tanpa
     * SIGNATURE_ROLES tanda tangan Sustain tidak pernah tercetak pada PDF, tanpa
     * panelist_roles ia tidak dapat dipilih sebagai panelis.
     */
    public function test_peran_sustain_terdaftar_pada_daftar_yang_tersebar(): void
    {
        $this->assertContains('admin_sustain', User::SIGNATURE_ROLES);
        $this->assertContains('technical_sustain', User::SIGNATURE_ROLES);
        $this->assertContains('technical_sustain', config('review.panelist_roles'));

        $panduan = collect(config('navigation'))
            ->firstWhere('route', 'panduan');
        $this->assertContains('admin_sustain', $panduan['roles']);
        $this->assertContains('technical_sustain', $panduan['roles']);

        foreach (['admin_sustain', 'technical_sustain'] as $code) {
            $this->assertTrue(
                view()->exists('panduan.partials.'.$code),
                "Panduan untuk {$code} belum ada; halamannya akan jatuh ke panduan Klien."
            );
        }
    }

    /**
     * Langkah workflow ISPO menyebut tim Sustain, bukan Admin/Teknis lama.
     */
    public function test_langkah_workflow_ispo_memakai_peran_sustain(): void
    {
        $this->seedAll();
        $scheme = CertificationScheme::where('code', 'ISPO')->firstOrFail();
        $steps = \App\Models\WorkflowTemplate::where('certification_scheme_id', $scheme->id)
            ->firstOrFail()
            ->steps()
            ->pluck('role_code', 'code');

        $this->assertSame('admin_sustain', $steps['admin_review']);
        $this->assertSame('technical_sustain', $steps['certificate_review']);
        $this->assertSame('technical_sustain', $steps['final_certificate']);

        $iso = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $stepsIso = \App\Models\WorkflowTemplate::where('certification_scheme_id', $iso->id)
            ->firstOrFail()
            ->steps()
            ->pluck('role_code', 'code');

        $this->assertSame('admin_application', $stepsIso['admin_review']);
        $this->assertSame('technical', $stepsIso['certificate_review']);
    }
}
