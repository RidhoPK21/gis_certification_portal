<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::create([
            'name' => 'Klien Uji',
            'email' => 'klien.uji@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);

        $user->roles()->attach(
            Role::where('code', 'client')->value('id')
        );

        return $user;
    }

    public function test_dashboard_klien_menampilkan_konten_sesuai_role(): void
    {
        $user = $this->makeClient();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Halo, Klien Uji')
            ->assertSee('Ajukan Sertifikasi')
            ->assertDontSee('Invoice & Pembayaran');
    }

    public function test_halaman_profil_dapat_dibuka(): void
    {
        $user = $this->makeClient();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Nama Perusahaan');
    }

    public function test_profil_dapat_diperbarui(): void
    {
        $user = $this->makeClient();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Klien Baru',
                'email' => 'klien.uji@example.com',
                'phone' => '08123456789',
                'company_name' => 'PT Contoh Sejahtera',
                'job_title' => 'Manajer Mutu',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Klien Baru',
            'company_name' => 'PT Contoh Sejahtera',
            'job_title' => 'Manajer Mutu',
        ]);
    }

    public function test_kata_sandi_dapat_diganti_dengan_kata_sandi_lama_benar(): void
    {
        $user = $this->makeClient();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'RahasiaKuat123',
                'password' => 'KataSandiBaru456',
                'password_confirmation' => 'KataSandiBaru456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
