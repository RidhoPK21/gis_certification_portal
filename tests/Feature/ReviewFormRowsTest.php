<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\ReviewService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menjaga hubungan antara baris tabel tinjauan (config review.form_rows) dan
 * checklist dokumen yang diunggah klien (scheme_required_documents).
 *
 * Keduanya bukan dua daftar dokumen yang terpisah: tabel tinjauan hanya memilih
 * sebagian dokumen checklist lewat kodenya, sesuai formulir cetak GIS. Bila kode
 * pada salah satu sisi bergeser, peninjau akan melihat baris tanpa berkas sama
 * sekali — dan itulah yang dicegah test ini.
 */
class ReviewFormRowsTest extends TestCase
{
    use RefreshDatabase;

    private const STAGES = ['administration', 'technical'];

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
    }

    private function client(): User
    {
        $user = User::create([
            'name' => 'Klien',
            'email' => 'klien'.Str::random(5).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    private function applicationFor(CertificationScheme $scheme, User $client): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji '.$scheme->code,
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'ROW-'.$scheme->code,
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Kunci form_rows adalah kode skema. Kunci yang salah ketik tidak memunculkan
     * galat apa pun — sistem diam-diam kembali memakai pengelompokan review_group
     * lama — sehingga harus ditangkap di sini.
     */
    public function test_setiap_kunci_form_rows_menunjuk_skema_yang_ada(): void
    {
        $this->seedAll();
        $schemeCodes = CertificationScheme::pluck('code')->all();

        $this->assertNotEmpty(config('review.form_rows'), 'config review.form_rows kosong.');

        foreach (array_keys(config('review.form_rows')) as $schemeCode) {
            $this->assertContains(
                $schemeCode,
                $schemeCodes,
                'form_rows memuat kode skema "'.$schemeCode.'" yang tidak ada di katalog skema.'
            );
        }
    }

    public function test_setiap_baris_tinjauan_ada_di_checklist_dokumen_klien(): void
    {
        $this->seedAll();

        foreach (config('review.form_rows') as $schemeCode => $stages) {
            $scheme = CertificationScheme::with('requiredDocuments')->where('code', $schemeCode)->firstOrFail();
            $documentCodes = $scheme->requiredDocuments->pluck('code')->all();

            foreach (self::STAGES as $stage) {
                foreach ($stages[$stage] ?? [] as $row) {
                    // Baris penilaian peninjau memang tidak punya dokumen padanan.
                    if (($row['document'] ?? true) === false) {
                        continue;
                    }

                    $this->assertContains(
                        $row['code'],
                        $documentCodes,
                        $schemeCode.' ('.$stage.'): baris "'.$row['code']
                            .'" tidak ada di checklist dokumen klien, jadi peninjau tidak akan menemukan berkasnya.'
                    );
                }
            }
        }
    }

    /**
     * Pemeriksaan sebenarnya: bukan sekadar kecocokan teks di config, melainkan
     * pemetaan yang dipakai form dan PDF benar-benar menemukan dokumennya.
     */
    public function test_pemetaan_baris_ke_dokumen_benar_benar_terhubung(): void
    {
        $this->seedAll();
        $client = $this->client();
        $reviews = app(ReviewService::class);

        foreach (array_keys(config('review.form_rows')) as $schemeCode) {
            $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();
            $application = $this->applicationFor($scheme, $client);

            foreach (self::STAGES as $stage) {
                $rows = $reviews->formRows($application, $stage);

                $this->assertNotEmpty($rows, $schemeCode.' ('.$stage.'): tabel tinjauan kosong.');

                foreach ($rows as $row) {
                    if (! $row->expects_document) {
                        continue;
                    }

                    $this->assertNotNull(
                        $row->document,
                        $schemeCode.' ('.$stage.'): baris "'.$row->code.'" tidak terhubung ke dokumen checklist.'
                    );
                }
            }
        }
    }
}
