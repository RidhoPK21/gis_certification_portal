<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\ReviewPdfService;
use Database\Seeders\IafNaceTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Keluaran ruang lingkup akreditasi pada tinjauan.
 *
 * PDF hanya diisi bila formulir FrM/Fr/FrO-nya memang punya barisnya, sedangkan
 * halaman tinjauan Admin dan Teknis selalu memperlihatkan pilihan klien untuk
 * seluruh skema.
 */
class IafNaceReviewOutputTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(IafNaceTaxonomySeeder::class);
    }

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

    private function applicationWithScope(string $schemeCode, string $status = 'admin_review'): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();

        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Lingkup Cetak',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'CTK-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        foreach ([
            'iaf_code' => '16',
            'iaf_description' => 'Beton, semen, kapur, plester, dll',
            'nace_code' => '23.6',
            'nace_description' => 'Industri pembuatan beton, semen dan gips',
        ] as $kode => $nilai) {
            $app->values()->create(['field_code' => $kode, 'value_text' => $nilai]);
        }

        return $app;
    }

    private function teksPdf(string $isi): string
    {
        preg_match_all('/Tm \((.*?)\) Tj/', $isi, $m);

        return implode("\n", array_map(
            fn ($t) => str_replace(['\\(', '\\)'], ['(', ')'], $t),
            $m[1]
        ));
    }

    public function test_formulir_lssm_mencetak_iaf_beserta_keterangan(): void
    {
        Storage::fake('private');
        $this->seedAll();

        $app = $this->applicationWithScope('ISO9001');
        $record = app(ReviewPdfService::class)->generate($app, $this->user('admin_application')->id);
        $teks = $this->teksPdf(Storage::disk('private')->get($record->file_path));

        $this->assertStringContainsString('IAF Code', $teks);
        $this->assertStringContainsString('16', $teks);
        $this->assertStringContainsString('Beton, semen, kapur, plester, dll', $teks);
    }

    public function test_formulir_lsml_mencetak_nace_beserta_keterangan(): void
    {
        Storage::fake('private');
        $this->seedAll();

        $app = $this->applicationWithScope('ISO14001');
        $record = app(ReviewPdfService::class)->generate($app, $this->user('admin_application')->id);
        $teks = $this->teksPdf(Storage::disk('private')->get($record->file_path));

        $this->assertStringContainsString('NACE Code', $teks);
        $this->assertStringContainsString('Industri pembuatan beton, semen dan gips', $teks);
    }

    public function test_haccp_tidak_lagi_mencetak_strip_pada_baris_iaf(): void
    {
        Storage::fake('private');
        $this->seedAll();

        // Sebelumnya HACCP mencetak baris IAF Code tanpa pernah menanyakannya ke klien.
        $app = $this->applicationWithScope('HACCP');
        $record = app(ReviewPdfService::class)->generate($app, $this->user('admin_application')->id);
        $teks = $this->teksPdf(Storage::disk('private')->get($record->file_path));

        $this->assertStringContainsString('IAF Code', $teks);
        $this->assertStringContainsString('Beton, semen, kapur, plester, dll', $teks);
    }

    public function test_formulir_lspro_dan_ispo_tidak_memuat_baris_iaf_nace(): void
    {
        Storage::fake('private');
        $this->seedAll();

        foreach (['SNI_LOKAL', 'ISPO'] as $kode) {
            $app = $this->applicationWithScope($kode);
            $record = app(ReviewPdfService::class)->generate($app, $this->user('admin_application')->id);
            $teks = $this->teksPdf(Storage::disk('private')->get($record->file_path));

            $this->assertStringNotContainsString('IAF Code', $teks, $kode.': formulir ini tidak punya baris IAF.');
            $this->assertStringNotContainsString('NACE Code', $teks, $kode.': formulir ini tidak punya baris NACE.');
        }
    }

    public function test_halaman_tinjauan_admin_dan_teknis_menampilkan_keterangan(): void
    {
        Storage::fake('private');
        $this->seedAll();

        foreach (['ISO9001', 'ISO14001', 'SNI_LOKAL', 'ISPO'] as $kode) {
            $app = $this->applicationWithScope($kode);

            $html = $this->actingAs($this->user('admin_application'))
                ->get(route('internal.applications.show', $app))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Industri pembuatan beton, semen dan gips', $html,
                $kode.': keterangan NACE tidak terlihat pada halaman tinjauan Admin.');

            $app->update(['status' => 'technical_review', 'current_step' => 'technical_review']);

            $htmlTeknis = $this->actingAs($this->user('technical'))
                ->get(route('technical.reviews.show', $app))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Industri pembuatan beton, semen dan gips', $htmlTeknis,
                $kode.': keterangan NACE tidak terlihat pada halaman tinjauan Teknis.');
        }
    }
}
