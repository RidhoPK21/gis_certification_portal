<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\IafCode;
use App\Models\NaceCode;
use App\Models\Role;
use App\Models\SchemeField;
use App\Models\User;
use Database\Seeders\IafNaceTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dropdown bertingkat IAF -> NACE pada form permohonan klien, beserta
 * penjagaannya di sisi server.
 */
class IafNaceClientFormTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(IafNaceTaxonomySeeder::class);
    }

    private function client(): User
    {
        $user = User::create([
            'name' => 'Klien '.Str::random(3),
            'email' => 'klien'.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    private function draft(User $client, CertificationScheme $scheme): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'draft',
            'current_step' => 'application_form',
            'company_name' => 'PT Lingkup',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'LKP-'.Str::random(5),
            'order_date' => today(),
        ]);
    }

    public function test_seluruh_skema_merender_dropdown_bertingkat(): void
    {
        $this->seedAll();
        $client = $this->client();

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $app = $this->draft($client, $scheme);

            $html = $this->actingAs($client)
                ->get(route('client.applications.edit', $app))
                ->assertOk()
                ->getContent();

            $konteks = $scheme->code.': ';

            $this->assertStringContainsString('js-iaf-code', $html, $konteks.'dropdown IAF tidak dirender.');
            $this->assertStringContainsString('js-nace-code', $html, $konteks.'dropdown NACE tidak dirender.');
            $this->assertStringContainsString('data-iaf="16"', $html, $konteks.'opsi NACE tidak membawa penanda IAF induknya.');
            $this->assertStringContainsString('Industri pembuatan beton, semen dan gips', $html, $konteks.'keterangan NACE tidak tersedia.');

            /*
             * Pengisian otomatis Lingkup industri ikut diperiksa di sini karena
             * yang menyalakannya adalah dropdown NACE. Lima skema (HACCP, tiga
             * SNI, ISPO) memang tidak punya field industry_scope — di sana
             * penyalurnya harus diam, bukan menabrak field lain.
             */
            $this->assertStringContainsString('isiLingkupIndustri', $html, $konteks.'pengisian otomatis Lingkup industri hilang.');

            $punyaLingkup = SchemeField::whereHas(
                'section',
                fn ($q) => $q->where('certification_scheme_id', $scheme->id)
            )->where('code', 'industry_scope')->exists();

            $this->assertSame(
                $punyaLingkup,
                str_contains($html, 'id="input-industry_scope"'),
                $konteks.'keberadaan field Lingkup industri tidak sesuai katalog.'
            );
        }
    }

    public function test_kode_nonaktif_tidak_ditawarkan_ke_klien(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        NaceCode::whereHas('iafCode', fn ($q) => $q->where('code', '16'))
            ->where('code', '23.5')
            ->update(['is_active' => false]);

        $html = $this->actingAs($client)
            ->get(route('client.applications.edit', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Industri pembuatan beton, semen dan gips', $html);
        $this->assertStringNotContainsString('Industri Semen, kapur dan gips', $html);
    }

    public function test_pasangan_valid_tersimpan_beserta_keterangannya(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        $this->actingAs($client)->put(route('client.applications.update', $app), [
            'fields' => [
                'iaf_code' => '16',
                'iaf_description' => 'Beton, semen, kapur, plester, dll',
                'nace_code' => '23.6',
                'nace_description' => 'Industri pembuatan beton, semen dan gips',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id, 'field_code' => 'iaf_code', 'value_text' => '16',
        ]);
        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id, 'field_code' => 'nace_code', 'value_text' => '23.6',
        ]);
        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id,
            'field_code' => 'nace_description',
            'value_text' => 'Industri pembuatan beton, semen dan gips',
        ]);
    }

    public function test_kode_di_luar_acuan_ditolak(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        $this->actingAs($client)->put(route('client.applications.update', $app), [
            'fields' => ['iaf_code' => '999', 'nace_code' => '88.8'],
        ])->assertSessionHasErrors('fields.iaf_code');

        $this->assertDatabaseMissing('application_values', [
            'application_id' => $app->id, 'field_code' => 'iaf_code', 'value_text' => '999',
        ]);
    }

    public function test_kode_nonaktif_ditolak_meski_ada_di_tabel(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        IafCode::where('code', '16')->update(['is_active' => false]);

        $this->actingAs($client)->put(route('client.applications.update', $app), [
            'fields' => ['iaf_code' => '16'],
        ])->assertSessionHasErrors('fields.iaf_code');
    }

    /**
     * Isi seluruh field wajib skema dengan nilai sekadarnya, supaya submit
     * lolos sampai ke pemeriksaan yang sedang diuji.
     */
    private function isiFieldWajib(CertificationApplication $app): void
    {
        $scheme = app(\App\Services\DynamicFormService::class)->schemeForApplication($app->fresh());

        foreach ($scheme->sections as $section) {
            foreach ($section->fields as $field) {
                if (! $field->is_required) {
                    continue;
                }
                if (in_array($field->code, ['iaf_code', 'nace_code'], true)) {
                    continue;
                }

                $nilai = match ($field->type) {
                    'select', 'radio' => $field->options->first()?->value ?? 'ya',
                    'number', 'currency' => '10',
                    'date' => '2026-09-01',
                    'email' => 'uji@contoh.test',
                    'url' => 'https://contoh.test',
                    'boolean' => '1',
                    default => 'Diisi untuk pengujian',
                };

                $app->values()->updateOrCreate(['field_code' => $field->code], ['value_text' => $nilai]);
            }
        }
    }

    public function test_pasangan_iaf_nace_yang_tidak_selaras_ditolak_saat_submit(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        $this->isiFieldWajib($app);

        /*
         * 23.6 memang ada pada tabel, tetapi milik IAF 16 — bukan IAF 1. Aturan
         * exists saja tidak menangkap ini.
         */
        $app->values()->updateOrCreate(['field_code' => 'iaf_code'], ['value_text' => '1']);
        $app->values()->updateOrCreate(['field_code' => 'nace_code'], ['value_text' => '23.6']);

        $this->actingAs($client)
            ->post(route('client.applications.submit', $app))
            ->assertSessionHasErrors('fields.nace_code');

        $this->assertSame('draft', $app->refresh()->status);
    }

    public function test_kode_nace_yang_sama_di_dua_iaf_tetap_diterima(): void
    {
        $this->seedAll();
        $client = $this->client();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = $this->draft($client, $scheme);

        // NACE 17 sah di IAF 7a maupun 7b.
        foreach (['7a', '7b'] as $iaf) {
            $app->values()->updateOrCreate(['field_code' => 'iaf_code'], ['value_text' => $iaf]);
            $app->values()->updateOrCreate(['field_code' => 'nace_code'], ['value_text' => '17']);

            $selaras = NaceCode::where('code', '17')
                ->whereHas('iafCode', fn ($q) => $q->where('code', $iaf))
                ->exists();

            $this->assertTrue($selaras, 'NACE 17 seharusnya sah di IAF '.$iaf);
        }
    }
}
