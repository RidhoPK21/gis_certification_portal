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
                'payment_stage' => 'belum_lunas',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['application_id' => $app->id, 'invoice_number' => 'INV-2026-001', 'payment_stage' => 'belum_lunas']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'invoice_issued']);
    }

    public function test_status_pembayaran_manual_memicu_workflow(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $app = $this->applicationInFinance($this->user('client'));

        // Tahap 2 -> order menjadi payment_partial & milestone tercatat.
        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-STAGE-001',
            'amount' => 3000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'tahap_2',
        ])->assertRedirect();

        $app->refresh();
        $this->assertSame('payment_partial', $app->status);
        $this->assertSame('tahap_2', $app->invoice->payment_stage);
        $this->assertSame(2, (int) $app->invoice->current_milestone);

        // Sudah Lunas -> order menjadi payment_completed.
        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-STAGE-001',
            'amount' => 3000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'lunas',
        ])->assertRedirect();

        $this->assertSame('payment_completed', $app->refresh()->status);
        $this->assertSame('lunas', $app->invoice->fresh()->payment_stage);
    }

    public function test_status_pembayaran_wajib_diisi(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $app = $this->applicationInFinance($this->user('client'));

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-NOSTAGE',
            'amount' => 1000000,
            'invoice_date' => now()->format('Y-m-d'),
        ])->assertSessionHasErrors('payment_stage');

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV-NOSTAGE']);
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
            'payment_stage' => 'belum_lunas',
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
            'payment_stage' => 'belum_lunas',
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
