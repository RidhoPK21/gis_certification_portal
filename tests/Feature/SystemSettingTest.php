<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $this->seed(RolePermissionSeeder::class);
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

    private function payload(array $override = []): array
    {
        return array_merge([
            'app_name' => 'Portal GIS',
            'app_tagline' => 'Sertifikasi Terpadu',
            'company_name' => 'PT Global Inspeksi Sertifikasi',
            'login_heading' => 'Selamat datang',
            'login_subheading' => 'Kelola sertifikasi Anda.',
            'footer_text' => '© 2026 GIS',
            'contact_address' => 'Jl. Contoh No. 1',
            'contact_phone' => '021-000000',
            'contact_email' => 'info@gis.test',
        ], $override);
    }

    public function test_superadmin_dapat_membuka_pengaturan(): void
    {
        $this->actingAs($this->user('superadmin'))
            ->get(route('superadmin.settings.index'))
            ->assertOk()
            ->assertSee('Pengaturan Sistem');
    }

    public function test_non_superadmin_ditolak(): void
    {
        $this->actingAs($this->user('finance'))
            ->get(route('superadmin.settings.index'))
            ->assertForbidden();
    }

    public function test_menyimpan_teks_identitas(): void
    {
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->put(route('superadmin.settings.update'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'app_name', 'value' => 'Portal GIS']);
        $this->assertDatabaseHas('settings', ['key' => 'footer_text', 'value' => '© 2026 GIS']);
        $this->assertSame('Portal GIS', app(SettingService::class)->get('app_name'));
    }

    public function test_nama_aplikasi_wajib_diisi(): void
    {
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->from(route('superadmin.settings.index'))
            ->put(route('superadmin.settings.update'), $this->payload(['app_name' => '']))
            ->assertSessionHasErrors('app_name');
    }

    public function test_unggah_logo_dan_favicon(): void
    {
        Storage::fake('branding');
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->put(route('superadmin.settings.update'), $this->payload([
                'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
                'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ]))
            ->assertRedirect();

        $settings = app(SettingService::class);
        $this->assertNotSame('', $settings->get('logo_path'));
        $this->assertNotSame('', $settings->get('favicon_path'));
        Storage::disk('branding')->assertExists($settings->get('logo_path'));
    }

    public function test_menolak_berkas_bukan_gambar(): void
    {
        Storage::fake('branding');
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->from(route('superadmin.settings.index'))
            ->put(route('superadmin.settings.update'), $this->payload([
                'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_menghapus_logo(): void
    {
        Storage::fake('branding');
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->put(route('superadmin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]));

        $this->assertNotSame('', app(SettingService::class)->get('logo_path'));

        $this->actingAs($admin)->put(route('superadmin.settings.update'), $this->payload([
            'remove_logo' => '1',
        ]));

        $this->assertSame('', app(SettingService::class)->get('logo_path'));
    }

    public function test_branding_tampil_di_halaman(): void
    {
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->put(route('superadmin.settings.update'), $this->payload([
            'app_name' => 'Nama Portal Baru',
            'footer_text' => 'Footer Uji Coba',
        ]));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nama Portal Baru')
            ->assertSee('Footer Uji Coba');
    }
}
