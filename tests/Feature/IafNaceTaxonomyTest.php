<?php

namespace Tests\Feature;

use App\Models\ApplicationValue;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\IafCode;
use App\Models\NaceCode;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IafNaceTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acuan ruang lingkup akreditasi KAN K-07.01 Rev.2 Lampiran 1 dan halaman
 * pengelolaannya di Superadmin.
 */
class IafNaceTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode).' '.Str::random(3),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function seedTaxonomy(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(IafNaceTaxonomySeeder::class);
    }

    public function test_seed_memuat_seluruh_lampiran_satu(): void
    {
        $this->seedTaxonomy();

        // 1 s.d. 39 dengan 7 dipecah menjadi 7a dan 7b.
        $this->assertSame(40, IafCode::count());
        $this->assertSame(112, NaceCode::count());

        // Contoh yang dipakai sebagai acuan perilaku dropdown.
        $iaf = IafCode::with('naceCodes')->where('code', '16')->firstOrFail();
        $this->assertSame('Beton, semen, kapur, plester, dll', $iaf->name_id);
        $this->assertSame(['23.5', '23.6'], $iaf->naceCodes->pluck('code')->all());
        $this->assertSame(
            'Industri pembuatan beton, semen dan gips',
            $iaf->naceCodes->firstWhere('code', '23.6')->name_id
        );
    }

    public function test_kode_nace_yang_sama_boleh_berada_di_dua_iaf(): void
    {
        $this->seedTaxonomy();

        // NACE 17 sah dimiliki IAF 7a maupun 7b menurut Lampiran 1.
        $this->assertSame(2, NaceCode::where('code', '17')->count());
    }

    public function test_seed_ulang_tidak_menghidupkan_kembali_kode_nonaktif(): void
    {
        $this->seedTaxonomy();

        IafCode::where('code', '39')->update(['is_active' => false]);
        $this->seed(IafNaceTaxonomySeeder::class);

        $this->assertFalse(IafCode::where('code', '39')->value('is_active'));
        $this->assertSame(40, IafCode::count(), 'Seed ulang seharusnya tidak menggandakan baris.');
    }

    public function test_superadmin_membuka_halaman_peran_lain_ditolak(): void
    {
        $this->seedTaxonomy();

        $this->actingAs($this->user('superadmin'))
            ->get(route('superadmin.iaf-nace.index'))
            ->assertOk()
            ->assertSee('Kode IAF');

        foreach (['admin_application', 'technical', 'client'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('superadmin.iaf-nace.index'))
                ->assertForbidden();
        }
    }

    public function test_superadmin_menambah_kode_iaf_dan_nace(): void
    {
        $this->seedTaxonomy();
        $superadmin = $this->user('superadmin');

        $this->actingAs($superadmin)->post(route('superadmin.iaf-nace.iaf.store'), [
            'code' => '40',
            'name_en' => 'Test sector',
            'name_id' => 'Sektor uji',
        ])->assertRedirect();

        $iaf = IafCode::where('code', '40')->firstOrFail();
        $this->assertTrue($iaf->is_active);

        $this->actingAs($superadmin)->post(route('superadmin.iaf-nace.nace.store'), [
            'iaf_code_id' => $iaf->id,
            'code' => '99.9',
            'name_en' => 'Test activity',
            'name_id' => 'Aktivitas uji',
        ])->assertRedirect();

        $this->assertDatabaseHas('nace_codes', ['iaf_code_id' => $iaf->id, 'code' => '99.9']);
    }

    public function test_kode_nace_ganda_dalam_satu_iaf_ditolak(): void
    {
        $this->seedTaxonomy();
        $iaf = IafCode::where('code', '16')->firstOrFail();

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.iaf-nace.nace.store'), [
                'iaf_code_id' => $iaf->id,
                'code' => '23.6',
                'name_en' => 'Duplikat',
                'name_id' => 'Duplikat',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(2, $iaf->naceCodes()->count());
    }

    public function test_menonaktifkan_menyembunyikan_dari_daftar_aktif(): void
    {
        $this->seedTaxonomy();
        $iaf = IafCode::where('code', '16')->firstOrFail();
        $nace = $iaf->naceCodes()->where('code', '23.5')->firstOrFail();

        $this->actingAs($this->user('superadmin'))
            ->put(route('superadmin.iaf-nace.nace.update', $nace), [
                'code' => $nace->code,
                'name_en' => $nace->name_en,
                'name_id' => $nace->name_id,
                // is_active tidak dikirim = tidak dicentang
            ])
            ->assertRedirect();

        $this->assertSame(2, $iaf->naceCodes()->count());
        $this->assertSame(1, $iaf->activeNaceCodes()->count());
    }

    public function test_hapus_ditolak_bila_kode_sudah_dipakai_permohonan(): void
    {
        $this->seedTaxonomy();
        $this->seed(SchemeCatalogSeeder::class);

        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'draft',
            'current_step' => 'application_form',
            'company_name' => 'PT Pemakai Kode',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'IAF-'.Str::random(5),
            'order_date' => today(),
        ]);

        ApplicationValue::create([
            'application_id' => $application->id,
            'field_code' => 'iaf_code',
            'value_text' => '16',
        ]);

        $iaf = IafCode::where('code', '16')->firstOrFail();

        $this->actingAs($this->user('superadmin'))
            ->delete(route('superadmin.iaf-nace.iaf.destroy', $iaf))
            ->assertStatus(422);

        $this->assertDatabaseHas('iaf_codes', ['id' => $iaf->id]);
    }

    public function test_hapus_berhasil_bila_kode_belum_pernah_dipakai(): void
    {
        $this->seedTaxonomy();
        $superadmin = $this->user('superadmin');

        $this->actingAs($superadmin)->post(route('superadmin.iaf-nace.iaf.store'), [
            'code' => '41',
            'name_en' => 'Sekali pakai',
            'name_id' => 'Sekali pakai',
        ])->assertRedirect();

        $iaf = IafCode::where('code', '41')->firstOrFail();

        $this->actingAs($superadmin)
            ->delete(route('superadmin.iaf-nace.iaf.destroy', $iaf))
            ->assertRedirect();

        $this->assertDatabaseMissing('iaf_codes', ['id' => $iaf->id]);
    }

    public function test_menghapus_iaf_ikut_menghapus_nace_di_bawahnya(): void
    {
        $this->seedTaxonomy();
        $superadmin = $this->user('superadmin');

        $this->actingAs($superadmin)->post(route('superadmin.iaf-nace.iaf.store'), [
            'code' => '42', 'name_en' => 'Induk', 'name_id' => 'Induk',
        ])->assertRedirect();

        $iaf = IafCode::where('code', '42')->firstOrFail();

        $this->actingAs($superadmin)->post(route('superadmin.iaf-nace.nace.store'), [
            'iaf_code_id' => $iaf->id, 'code' => '88.8', 'name_en' => 'Anak', 'name_id' => 'Anak',
        ])->assertRedirect();

        $naceId = NaceCode::where('code', '88.8')->value('id');

        $this->actingAs($superadmin)
            ->delete(route('superadmin.iaf-nace.iaf.destroy', $iaf))
            ->assertRedirect();

        $this->assertDatabaseMissing('nace_codes', ['id' => $naceId]);
    }
}
