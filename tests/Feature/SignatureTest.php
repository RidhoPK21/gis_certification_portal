<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    public function test_role_penanda_tangan_dapat_upload_esign(): void
    {
        Storage::fake('private');
        $admin = $this->user('admin_application');

        $this->actingAs($admin)
            ->post(route('profile.signature'), [
                'signature' => UploadedFile::fake()->image('ttd.png', 240, 90),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertNotNull($admin->signature_path);
        Storage::disk('private')->assertExists($admin->signature_path);
        $this->assertStringEndsWith('.jpg', $admin->signature_path);
    }

    public function test_upload_ulang_menimpa_file_lama(): void
    {
        Storage::fake('private');
        $admin = $this->user('technical');

        $this->actingAs($admin)->post(route('profile.signature'), [
            'signature' => UploadedFile::fake()->image('ttd1.png', 200, 80),
        ]);
        $first = $admin->refresh()->signature_path;

        $this->actingAs($admin)->post(route('profile.signature'), [
            'signature' => UploadedFile::fake()->image('ttd2.png', 200, 80),
        ]);
        $second = $admin->refresh()->signature_path;

        $this->assertNotSame($first, $second);
        Storage::disk('private')->assertMissing($first);
        Storage::disk('private')->assertExists($second);
    }

    public function test_role_tanpa_hak_ditolak_upload_esign(): void
    {
        Storage::fake('private');
        $finance = $this->user('finance');

        $this->actingAs($finance)
            ->post(route('profile.signature'), [
                'signature' => UploadedFile::fake()->image('ttd.png', 200, 80),
            ])
            ->assertForbidden();

        $this->assertNull($finance->refresh()->signature_path);
    }

    public function test_card_esign_hanya_tampil_untuk_role_penanda_tangan(): void
    {
        $auditor = $this->user('auditor');
        $this->actingAs($auditor)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tanda Tangan Elektronik');

        $finance = $this->user('finance');
        $this->actingAs($finance)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Tanda Tangan Elektronik');
    }

    public function test_esign_dapat_dihapus(): void
    {
        Storage::fake('private');
        $admin = $this->user('admin_application');

        $this->actingAs($admin)->post(route('profile.signature'), [
            'signature' => UploadedFile::fake()->image('ttd.png', 200, 80),
        ]);
        $path = $admin->refresh()->signature_path;
        $this->assertNotNull($path);

        $this->actingAs($admin)->delete(route('profile.signature.remove'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($admin->refresh()->signature_path);
        Storage::disk('private')->assertMissing($path);
    }
}
