<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanduanTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRoles(array $roleCodes, bool $isActive = true): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::create([
            'name' => 'Pengguna Uji ' . implode('_', $roleCodes),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => $isActive,
        ]);

        $roleIds = Role::whereIn('code', $roleCodes)->pluck('id');
        $user->roles()->attach($roleIds);

        return $user;
    }

    public function test_guest_diarahkan_ke_login_saat_buka_panduan(): void
    {
        $this->get(route('panduan'))
            ->assertRedirect(route('login'));
    }

    public function test_akun_tidak_aktif_tidak_dapat_buka_panduan(): void
    {
        $user = $this->makeUserWithRoles(['client'], false);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertRedirect(route('login'));
    }

    public function test_klien_hanya_melihat_panduan_klien(): void
    {
        $user = $this->makeUserWithRoles(['client']);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Panduan Klien')
            ->assertDontSee('Panduan Finance')
            ->assertDontSee('Panduan Auditor')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_admin_permohonan_hanya_melihat_panduan_admin_permohonan(): void
    {
        $user = $this->makeUserWithRoles(['admin_application']);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Panduan Admin Permohonan')
            ->assertDontSee('Panduan Klien')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_finance_hanya_melihat_panduan_finance(): void
    {
        $user = $this->makeUserWithRoles(['finance']);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Panduan Finance')
            ->assertDontSee('Panduan Auditor')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_auditor_melihat_panduan_auditor_dan_lingkup_penugasan(): void
    {
        $user = $this->makeUserWithRoles(['auditor']);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Panduan Auditor')
            ->assertSee('Lingkup Penugasan Auditor')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_tim_teknis_hanya_melihat_panduan_tim_teknis(): void
    {
        $user = $this->makeUserWithRoles(['technical']);

        $this->actingAs($user)
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Panduan Tim Teknis')
            ->assertDontSee('Panduan Klien')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_akun_multi_role_hanya_dapat_buka_panduan_role_yang_dimiliki(): void
    {
        $user = $this->makeUserWithRoles(['finance', 'auditor']);

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'auditor']))
            ->assertOk()
            ->assertSee('Panduan Auditor')
            ->assertDontSee('Panduan Superadmin');

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'superadmin']))
            ->assertOk()
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_superadmin_dapat_melihat_dan_berpindah_seluruh_panduan_role(): void
    {
        $user = $this->makeUserWithRoles(['superadmin']);

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'superadmin']))
            ->assertOk()
            ->assertSee('Panduan Superadmin');

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'client']))
            ->assertOk()
            ->assertSee('Panduan Klien');

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'auditor']))
            ->assertOk()
            ->assertSee('Panduan Auditor');
    }

    public function test_manipulasi_parameter_role_oleh_klien_tetap_menampilkan_panduan_klien(): void
    {
        $user = $this->makeUserWithRoles(['client']);

        $this->actingAs($user)
            ->get(route('panduan', ['role' => 'superadmin']))
            ->assertOk()
            ->assertSee('Panduan Klien')
            ->assertDontSee('Panduan Superadmin');
    }

    public function test_tombol_tindakan_cepat_menggunakan_route_valid_dan_tidak_404(): void
    {
        $user = $this->makeUserWithRoles(['client']);

        $response = $this->actingAs($user)->get(route('panduan'));
        $response->assertOk();
        $response->assertSee(route('client.applications.schemes'));
        $response->assertSee(route('client.applications.index'));
        $response->assertSee(route('client.corrective-actions.index'));
    }
}
