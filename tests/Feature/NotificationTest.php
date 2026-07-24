<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\PortalNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create([
            'name' => 'Klien Uji',
            'email' => 'klien'.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    public function test_lonceng_dan_badge_unread_tampil_di_header(): void
    {
        $user = $this->user();
        $service = app(PortalNotificationService::class);
        $service->send($user, 'test', 'Notif Satu', 'Isi notif satu.');
        $service->send($user, 'test', 'Notif Dua', 'Isi notif dua.');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="notif-toggle"', false)
            ->assertSee('notif-badge', false)
            ->assertSee('Notif Satu');
    }

    public function test_notifikasi_dapat_ditandai_dibaca(): void
    {
        $user = $this->user();
        $notif = app(PortalNotificationService::class)->send($user, 'test', 'Judul', 'Pesan');

        $this->actingAs($user)->post(route('notifications.read', $notif))->assertRedirect();

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_tandai_semua_dibaca_mengosongkan_unread(): void
    {
        $user = $this->user();
        $service = app(PortalNotificationService::class);
        $service->send($user, 'test', 'A', 'a');
        $service->send($user, 'test', 'B', 'b');

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, \App\Models\PortalNotification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_halaman_notifikasi_lama_sudah_tidak_ada(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('notifications.index'));
    }
}
