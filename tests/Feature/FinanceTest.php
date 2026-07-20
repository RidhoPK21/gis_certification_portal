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

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function applicationInFinance(User $client): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'invoice_process',
            'current_step' => 'invoice_process',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'FIN-001',
            'order_date' => today(),
        ]);
    }

    public function test_finance_dapat_melihat_antrean(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $this->applicationInFinance($this->user('client'));

        $this->actingAs($finance)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Invoice &amp; Pembayaran', false);
    }

    public function test_klien_ditolak_di_modul_finance(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)
            ->get(route('finance.index'))
            ->assertForbidden();
    }

    public function test_finance_dapat_menerbitkan_invoice(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $client = $this->user('client');
        $app = $this->applicationInFinance($client);

        $this->actingAs($finance)
            ->post(route('finance.invoice', $app), [
                'invoice_number' => 'INV-2026-001',
                'amount' => 5000000,
                'invoice_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['application_id' => $app->id, 'invoice_number' => 'INV-2026-001']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'invoice_issued']);
    }

    public function test_pembayaran_lunas_memindahkan_status_ke_payment_completed(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $client = $this->user('client');
        $app = $this->applicationInFinance($client);

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-2026-002',
            'amount' => 1000000,
            'invoice_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($finance)->post(route('finance.payment', $app), [
            'milestone' => 1,
            'amount' => 1000000,
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'verified',
        ])->assertRedirect();

        $app->refresh();
        $this->assertSame('payment_completed', $app->status);
        $this->assertSame('paid', $app->invoice->fresh()->payment_status);
        $this->assertDatabaseHas('payment_status_history', ['invoice_id' => $app->invoice->id, 'to_status' => 'paid']);
    }

    public function test_pembayaran_sebagian_menjadi_payment_partial(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $app = $this->applicationInFinance($this->user('client'));

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-2026-003',
            'amount' => 2000000,
            'invoice_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($finance)->post(route('finance.payment', $app), [
            'milestone' => 1,
            'amount' => 500000,
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'verified',
        ])->assertRedirect();

        $this->assertSame('payment_partial', $app->refresh()->status);
    }
}
