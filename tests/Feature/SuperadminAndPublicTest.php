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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperadminAndPublicTest extends TestCase
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

    public function test_superadmin_melihat_master_produk_sni_terseed(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->get(route('superadmin.sni-products.index'))
            ->assertOk()
            ->assertSee('SNI-DEMO-001');
    }

    public function test_non_superadmin_ditolak_di_produk_sni(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)
            ->get(route('superadmin.sni-products.index'))
            ->assertForbidden();
    }

    public function test_superadmin_menambah_produk_sni(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');

        $this->actingAs($admin)
            ->post(route('superadmin.sni-products.store'), [
                'product_code' => 'P-XYZ',
                'product_name' => 'Produk Uji',
                'certification_system' => 'System 5',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sni_product_master', ['product_code' => 'P-XYZ']);
    }

    public function test_import_produk_sni_dari_csv(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');
        $csv = "kode_produk,nama_produk,kategori,nomor_sni\nP-IMP-1,Produk Impor,Pangan,SNI 1234\n";
        $file = UploadedFile::fake()->createWithContent('produk.csv', $csv);

        $this->actingAs($admin)
            ->post(route('superadmin.sni-products.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('sni_product_master', ['product_code' => 'P-IMP-1', 'product_name' => 'Produk Impor']);
    }

    public function test_superadmin_mengubah_role_user(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');
        $target = $this->user('client');
        $financeRoleId = Role::where('code', 'finance')->value('id');

        $this->actingAs($admin)
            ->put(route('superadmin.users.update', $target), [
                'roles' => [$financeRoleId],
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('finance'));
    }

    public function test_superadmin_melihat_audit_trail(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');
        // Aksi menghasilkan log.
        $this->actingAs($admin)->post(route('superadmin.sni-products.store'), [
            'product_code' => 'P-LOG', 'product_name' => 'Log', 'certification_system' => 'System 5',
        ]);

        $this->actingAs($admin)
            ->get(route('superadmin.audit-trail.index'))
            ->assertOk()
            ->assertSee('sni_product.created');
    }

    public function test_public_tracking_menemukan_order(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(), 'client_id' => $client->id, 'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version, 'status' => 'admin_review', 'current_step' => 'admin_review',
            'company_name' => 'PT Uji', 'contact_email' => 'a@b.c', 'order_number' => 'PUB-001', 'submitted_at' => now(),
        ]);

        // Halaman publik terbuka tanpa login.
        $this->get(route('public.home'))->assertOk();

        $this->post(route('public.track'), ['order_number' => 'PUB-001'])
            ->assertOk()
            ->assertSee('PUB-001')
            ->assertSee('Review Admin');
    }

    public function test_public_tracking_order_tidak_ditemukan(): void
    {
        $this->seedAll();

        $this->post(route('public.track'), ['order_number' => 'TIDAK-ADA'])
            ->assertRedirect()
            ->assertSessionHasErrors('order_number');
    }
}
