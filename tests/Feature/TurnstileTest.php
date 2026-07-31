<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TurnstileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create([
            'name' => 'Klien Uji',
            'email' => 'klien' . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'client')->value('id'));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function enableTurnstile(): void
    {
        config([
            'turnstile.site_key' => 'site-key-uji',
            'turnstile.secret_key' => 'secret-key-uji',
        ]);
    }

    public function test_nonaktif_bila_kunci_kosong(): void
    {
        config(['turnstile.site_key' => null, 'turnstile.secret_key' => null]);

        $this->assertFalse(app(TurnstileService::class)->enabled());
    }

    public function test_login_tetap_berjalan_saat_turnstile_nonaktif(): void
    {
        config(['turnstile.site_key' => null, 'turnstile.secret_key' => null]);
        $user = $this->client();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_ditolak_bila_token_turnstile_tidak_valid(): void
    {
        $this->enableTurnstile();
        Http::fake([
            '*challenges.cloudflare.com*' => Http::response(['success' => false], 200),
        ]);
        $user = $this->client();

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'RahasiaKuat123',
                TurnstileService::FIELD => 'token-palsu',
            ])
            ->assertSessionHasErrors(TurnstileService::FIELD);

        $this->assertGuest();
    }

    public function test_login_ditolak_bila_token_turnstile_kosong(): void
    {
        $this->enableTurnstile();
        Http::fake();
        $user = $this->client();

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'RahasiaKuat123',
            ])
            ->assertSessionHasErrors(TurnstileService::FIELD);

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_login_berhasil_bila_token_turnstile_valid(): void
    {
        $this->enableTurnstile();
        Http::fake([
            '*challenges.cloudflare.com*' => Http::response(['success' => true], 200),
        ]);
        $user = $this->client();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
            TurnstileService::FIELD => 'token-benar',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_registrasi_ditolak_bila_token_turnstile_tidak_valid(): void
    {
        $this->enableTurnstile();
        Http::fake([
            '*challenges.cloudflare.com*' => Http::response(['success' => false], 200),
        ]);
        $this->seed(RolePermissionSeeder::class);

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'Calon Klien',
                'company_name' => 'PT Uji Bot',
                'email' => 'calon.klien@example.com',
                'password' => 'RahasiaKuat2026',
                'password_confirmation' => 'RahasiaKuat2026',
                TurnstileService::FIELD => 'token-palsu',
            ])
            ->assertSessionHasErrors(TurnstileService::FIELD);

        $this->assertDatabaseMissing('users', ['email' => 'calon.klien@example.com']);
    }

    public function test_ditolak_bila_layanan_turnstile_tidak_dapat_dihubungi(): void
    {
        $this->enableTurnstile();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $this->assertFalse(
            app(TurnstileService::class)->verify('token-apa-saja', '127.0.0.1')
        );
    }
}
