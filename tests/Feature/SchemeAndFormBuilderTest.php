<?php

namespace Tests\Feature;

use App\Models\CertificationScheme;
use App\Models\FormConfigurationVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemeAndFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function scheme(): CertificationScheme
    {
        $this->seed(SchemeCatalogSeeder::class);

        return CertificationScheme::orderBy('sort_order')->firstOrFail();
    }

    public function test_superadmin_dapat_melihat_daftar_skema(): void
    {
        $admin = $this->user('superadmin');
        $scheme = $this->scheme();

        $this->actingAs($admin)
            ->get(route('superadmin.schemes.index'))
            ->assertOk()
            ->assertSee($scheme->short_name);
    }

    public function test_non_superadmin_ditolak(): void
    {
        $client = $this->user('client');
        $this->scheme();

        $this->actingAs($client)
            ->get(route('superadmin.schemes.index'))
            ->assertForbidden();
    }

    public function test_superadmin_dapat_membuka_form_builder(): void
    {
        $admin = $this->user('superadmin');
        $scheme = $this->scheme();

        $this->actingAs($admin)
            ->get(route('superadmin.form-builder.edit', $scheme))
            ->assertOk()
            ->assertSee('Form Builder');
    }

    public function test_menambah_section_membuat_versi_form_baru(): void
    {
        $admin = $this->user('superadmin');
        $scheme = $this->scheme();
        $versiAwal = $scheme->form_version;

        $this->actingAs($admin)
            ->post(route('superadmin.form-builder.sections.store', $scheme), [
                'code' => 'section_uji',
                'title' => 'Section Uji',
                'description' => 'Section tambahan untuk pengujian.',
                'sort_order' => 999,
            ])
            ->assertRedirect();

        $scheme->refresh();

        $this->assertSame($versiAwal + 1, $scheme->form_version);
        $this->assertTrue($scheme->sections()->where('code', 'section_uji')->exists());
        $this->assertTrue(
            FormConfigurationVersion::where('certification_scheme_id', $scheme->id)
                ->where('version', $scheme->form_version)
                ->exists()
        );
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'form_configuration.published',
        ]);
    }

    public function test_menambah_field_dengan_opsi(): void
    {
        $admin = $this->user('superadmin');
        $scheme = $this->scheme();
        $section = $scheme->sections()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('superadmin.form-builder.fields.store', $scheme), [
                'scheme_section_id' => $section->id,
                'code' => 'field_uji',
                'label' => 'Field Uji',
                'type' => 'select',
                'sort_order' => 500,
                'options_text' => "ya|Ya\ntidak|Tidak",
            ])
            ->assertRedirect();

        $field = $section->fields()->where('code', 'field_uji')->firstOrFail();

        $this->assertSame(2, $field->options()->count());
    }

    public function test_menambah_dokumen_wajib(): void
    {
        $admin = $this->user('superadmin');
        $scheme = $this->scheme();

        $this->actingAs($admin)
            ->post(route('superadmin.form-builder.documents.store', $scheme), [
                'code' => 'dok_uji',
                'name' => 'Dokumen Uji',
                'requirement' => 'required',
                'review_group' => 'administration',
                'allowed_extensions_text' => 'pdf,jpg',
                'max_size_mb' => 10,
                'sort_order' => 500,
            ])
            ->assertRedirect();

        $this->assertTrue($scheme->requiredDocuments()->where('code', 'dok_uji')->exists());
    }
}
