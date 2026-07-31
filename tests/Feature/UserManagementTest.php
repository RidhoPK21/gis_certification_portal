<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\EmailOtp;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function superadmin(): User
    {
        $this->seed(RolePermissionSeeder::class);

        // Dua superadmin agar penghapusan tidak terblokir aturan "superadmin terakhir".
        $this->user('superadmin');

        return $this->user('superadmin');
    }

    public function test_daftar_user_menampilkan_tombol_kelola(): void
    {
        $admin = $this->superadmin();
        $target = $this->user('finance');

        $this->actingAs($admin)
            ->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee('Kelola')
            ->assertSee(route('superadmin.users.edit', $target), false);
    }

    public function test_halaman_kelola_menampilkan_seluruh_aksi(): void
    {
        $admin = $this->superadmin();
        $target = $this->user('finance');

        $this->actingAs($admin)
            ->get(route('superadmin.users.edit', $target))
            ->assertOk()
            ->assertSee($target->email)
            ->assertSee('Role &amp; Status', false)
            ->assertSee('Kirim Kode Reset')
            ->assertSee('Tetapkan kata sandi manual')
            ->assertSee('Hapus Akun Ini');
    }

    public function test_halaman_kelola_menyembunyikan_hapus_bila_punya_permohonan(): void
    {
        $admin = $this->superadmin();
        $this->seed(SchemeCatalogSeeder::class);
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'draft',
            'current_step' => 'draft',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
        ]);

        $this->actingAs($admin)
            ->get(route('superadmin.users.edit', $client))
            ->assertOk()
            ->assertDontSee('Hapus Akun Ini')
            ->assertSee('tidak dapat dihapus');
    }

    public function test_superadmin_dapat_menghapus_akun_tanpa_permohonan(): void
    {
        $admin = $this->superadmin();
        $target = $this->user('client');

        $this->actingAs($admin)
            ->delete(route('superadmin.users.destroy', $target))
            ->assertRedirect(route('superadmin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.deleted']);
    }

    public function test_akun_dengan_permohonan_tidak_dapat_dihapus(): void
    {
        $admin = $this->superadmin();
        $this->seed(SchemeCatalogSeeder::class);
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'draft',
            'current_step' => 'draft',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
        ]);

        $this->actingAs($admin)
            ->from(route('superadmin.users.index'))
            ->delete(route('superadmin.users.destroy', $client))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $client->id]);
    }

    public function test_tidak_dapat_menghapus_akun_sendiri(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->from(route('superadmin.users.index'))
            ->delete(route('superadmin.users.destroy', $admin))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_superadmin_nonaktif_boleh_dihapus(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $aktif = $this->user('superadmin');
        $nonaktif = $this->user('superadmin');
        $nonaktif->forceFill(['is_active' => false])->save();

        $this->actingAs($aktif)
            ->delete(route('superadmin.users.destroy', $nonaktif))
            ->assertRedirect(route('superadmin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $nonaktif->id]);
    }

    public function test_superadmin_dapat_mengirim_kode_reset_kata_sandi(): void
    {
        $admin = $this->superadmin();
        $target = $this->user('finance');

        $this->actingAs($admin)
            ->post(route('superadmin.users.password-reset', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('email_otps', [
            'user_id' => $target->id,
            'purpose' => EmailOtp::PURPOSE_PASSWORD_RESET,
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.password_reset_sent']);
    }

    public function test_superadmin_dapat_menetapkan_kata_sandi_manual(): void
    {
        $admin = $this->superadmin();
        $target = $this->user('auditor');

        $this->actingAs($admin)
            ->put(route('superadmin.users.password', $target), [
                'password' => 'KataSandiBaru2026',
                'password_confirmation' => 'KataSandiBaru2026',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('KataSandiBaru2026', $target->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.password_set_by_superadmin']);
    }

    public function test_pengguna_dapat_reset_kata_sandi_dengan_kode(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $target = $this->user('technical');

        // Halaman reset khusus tamu, jadi kode dibuat langsung tanpa login superadmin.
        $code = app(OtpService::class)->generate($target, EmailOtp::PURPOSE_PASSWORD_RESET);

        $this->post(route('password.reset.submit'), [
            'email' => $target->email,
            'code' => $code,
            'password' => 'SandiResetBaru2026',
            'password_confirmation' => 'SandiResetBaru2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('SandiResetBaru2026', $target->fresh()->password));

        $otp = EmailOtp::where('user_id', $target->id)
            ->where('purpose', EmailOtp::PURPOSE_PASSWORD_RESET)
            ->latest('id')
            ->firstOrFail();
        $this->assertNotNull($otp->consumed_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.password_reset_completed']);
    }

    public function test_reset_ditolak_bila_kode_salah(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $target = $this->user('technical');
        app(OtpService::class)->generate($target, EmailOtp::PURPOSE_PASSWORD_RESET);

        $this->from(route('password.reset.show'))
            ->post(route('password.reset.submit'), [
                'email' => $target->email,
                'code' => '000000',
                'password' => 'SandiResetBaru2026',
                'password_confirmation' => 'SandiResetBaru2026',
            ])
            ->assertSessionHasErrors('code');

        $this->assertFalse(Hash::check('SandiResetBaru2026', $target->fresh()->password));
    }

    public function test_non_superadmin_ditolak(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $finance = $this->user('finance');
        $target = $this->user('client');

        $this->actingAs($finance)
            ->delete(route('superadmin.users.destroy', $target))
            ->assertForbidden();
    }
}
