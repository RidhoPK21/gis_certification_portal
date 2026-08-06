<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationSubmissionService;
use App\Services\DynamicFormService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menguji jalur AJAX (data-ajax) pada semua form upload:
 * request dengan Accept application/json harus dibalas JSON,
 * sehingga halaman tidak perlu reload.
 */
class AjaxUploadTest extends TestCase
{
    use RefreshDatabase;

    /** Header yang dikirim JavaScript saat submit form data-ajax. */
    private array $ajax = [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
            'company_name' => 'PT Uji',
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    public function test_upload_dokumen_klien_via_ajax_membalas_json(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $app = app(ApplicationSubmissionService::class)->createDraft($client->id, $scheme->id, [
            'company_name' => 'PT Uji',
            'applicant_name' => 'Budi',
            'contact_email' => 'budi@uji.test',
            'contact_phone' => '0811',
            'form_version' => $scheme->form_version,
        ]);

        $snapshot = app(DynamicFormService::class)->schemeForApplication($app);

        /*
         * Formulir Wajib GIS terkunci sampai templatenya dibagikan, jadi yang
         * diuji di sini dokumen milik perusahaan.
         */
        $companyDocuments = $snapshot->requiredDocuments
            ->filter(fn ($document) => ($document->document_group ?? 'company') !== 'gis_form');
        $doc = $companyDocuments->firstWhere('requirement', 'required') ?? $companyDocuments->first();

        $response = $this->actingAs($client)->post(route('client.documents.store', $app), [
            'document_code' => $doc->code,
            'file' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ], $this->ajax);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'document_code' => $doc->code,
                'version' => 1,
                'review_status' => 'pending',
            ]);
    }

    public function test_upload_dokumen_klien_via_ajax_validasi_gagal_membalas_422_json(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $app = app(ApplicationSubmissionService::class)->createDraft($client->id, $scheme->id, [
            'company_name' => 'PT Uji',
            'applicant_name' => 'Budi',
            'contact_email' => 'budi@uji.test',
            'contact_phone' => '0811',
            'form_version' => $scheme->form_version,
        ]);

        // Tanpa file sama sekali → validasi harus gagal dengan JSON 422.
        $this->actingAs($client)->post(route('client.documents.store', $app), [
            'document_code' => 'DOC-APA-SAJA',
        ], $this->ajax)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_invoice_finance_via_ajax_membalas_json(): void
    {
        $this->seedAll();
        $finance = $this->user('finance');
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'invoice_process',
            'current_step' => 'invoice_process',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'FIN-AJAX-1',
            'order_date' => today(),
        ]);

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-AJAX-1',
            'amount' => 1000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'belum_lunas',
        ], $this->ajax)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('invoices', ['application_id' => $app->id, 'invoice_number' => 'INV-AJAX-1']);
    }

    public function test_import_sni_via_ajax_membalas_json(): void
    {
        $this->seedAll();
        $admin = $this->user('superadmin');
        $csv = "kode_produk,nama_produk,kategori,nomor_sni\nP-AJAX-1,Produk Ajax,Pangan,SNI 9999\n";
        $file = UploadedFile::fake()->createWithContent('produk.csv', $csv);

        $this->actingAs($admin)->post(route('superadmin.sni-products.import'), [
            'file' => $file,
        ], $this->ajax)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sni_product_master', ['product_code' => 'P-AJAX-1']);
    }

    public function test_form_biasa_non_ajax_tetap_redirect(): void
    {
        // Memastikan fallback non-AJAX tidak berubah (tetap redirect + flash).
        $this->seedAll();
        $finance = $this->user('finance');
        $client = $this->user('client');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'invoice_process',
            'current_step' => 'invoice_process',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'FIN-REDIR-1',
            'order_date' => today(),
        ]);

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-REDIR-1',
            'amount' => 1000000,
            'invoice_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
    }

    public function test_validasi_dan_completion_field_file_dengan_file_lama_tersimpan(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $scheme = null;
        $fileField = null;
        foreach (CertificationScheme::all() as $s) {
            $f = $s->sections->flatMap->fields->firstWhere('type', 'file');
            if ($f) {
                $scheme = $s;
                $fileField = $f;
                break;
            }
        }
        $this->assertNotNull($fileField, 'Harus ada skema dengan minimal satu field file');

        $app = app(ApplicationSubmissionService::class)->createDraft($client->id, $scheme->id, [
            'company_name' => 'PT Uji',
            'applicant_name' => 'Budi',
            'contact_email' => 'budi@uji.test',
            'contact_phone' => '0811',
            'form_version' => $scheme->form_version,
        ]);

        $forms = app(DynamicFormService::class);
        $schema = $forms->schemeForApplication($app);

        $values = [
            $fileField->code => [
                'path' => 'applications/' . $app->id . '/fields/' . $fileField->code . '/file_lama.pdf',
                'original_name' => 'file_lama.pdf',
            ],
        ];

        $rules = $forms->validationRules($schema, $values, true);
        $this->assertArrayHasKey('fields.' . $fileField->code, $rules);

        $validator = validator(['fields' => $values], $rules, $forms->validationMessages(), $forms->validationAttributes($schema, $values));
        $errors = $validator->errors();
        $this->assertFalse($errors->has('fields.' . $fileField->code), 'Validasi untuk field ' . $fileField->label . ' tidak boleh gagal saat ada file lama tersimpan: ' . implode(', ', $errors->get('fields.' . $fileField->code)));

        $this->assertTrue($forms->isFieldFilled($fileField, $values[$fileField->code]));
    }
}
