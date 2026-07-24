<?php

namespace Tests\Feature;

use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\SniProductGroup;
use App\Models\User;
use App\Services\ApplicationSubmissionService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\SniProductTaxonomySeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SniProductTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(SniProductTaxonomySeeder::class);
    }

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    public function test_taksonomi_awal_ter_seed(): void
    {
        $this->seedAll();
        $this->assertSame(12, SniProductGroup::count());
        $this->assertDatabaseHas('sni_product_groups', ['name' => 'Kelistrikan, Elektronika & Luminer', 'mandatory_type' => 'wajib']);
        $this->assertDatabaseHas('sni_product_categories', ['name' => 'Kipas Angin']);
    }

    public function test_form_sni_menampilkan_dropdown_produk(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $scheme = CertificationScheme::where('review_template', 'sni')->firstOrFail();
        $app = app(ApplicationSubmissionService::class)->createDraft($client->id, $scheme->id, [
            'company_name' => 'PT Uji', 'applicant_name' => 'Uji', 'contact_email' => 'uji@uji.test',
            'contact_phone' => '0811', 'form_version' => $scheme->form_version,
        ]);

        $this->actingAs($client)->get(route('client.applications.edit', $app))
            ->assertOk()
            ->assertSee('sni-product-group', false)
            ->assertSee('sni-product-category', false)
            ->assertSee('Kelistrikan, Elektronika &amp; Luminer', false)
            ->assertSee('Kipas Angin');
    }

    public function test_superadmin_dapat_menambah_grup_dan_kategori(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');

        $this->actingAs($admin)->post(route('superadmin.sni-taxonomy.groups.store'), [
            'name' => 'Grup Uji Baru', 'mandatory_type' => 'sukarela',
        ])->assertRedirect()->assertSessionHas('success');

        $group = SniProductGroup::where('name', 'Grup Uji Baru')->firstOrFail();

        $this->actingAs($admin)->post(route('superadmin.sni-taxonomy.categories.store'), [
            'sni_product_group_id' => $group->id, 'name' => 'Kategori Uji Baru',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('sni_product_categories', [
            'sni_product_group_id' => $group->id, 'name' => 'Kategori Uji Baru',
        ]);
    }

    public function test_halaman_kelola_taksonomi_terpaginate(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');

        // 12 grup ter-seed, paginate 8 per halaman -> ada halaman ke-2.
        $this->actingAs($admin)->get(route('superadmin.sni-taxonomy.index'))
            ->assertOk()
            ->assertSee('Halaman 1 / 2');
    }

    public function test_non_superadmin_tidak_bisa_akses_kelola_taksonomi(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)->get(route('superadmin.sni-taxonomy.index'))->assertForbidden();
    }
}
