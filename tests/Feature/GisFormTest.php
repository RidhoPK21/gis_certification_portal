<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GisFormRequest;
use App\Models\GisFormTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationSubmissionService;
use Database\Seeders\GisFormTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class GisFormTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(GisFormTemplateSeeder::class);
    }

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . Str::random(5) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
            'company_name' => 'PT Uji',
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function scheme(): CertificationScheme
    {
        return CertificationScheme::where('code', 'ISO9001')->firstOrFail();
    }

    private function draftFor(User $client): CertificationApplication
    {
        return app(ApplicationSubmissionService::class)->createDraft(
            $client->id,
            $this->scheme()->id,
            [
                'company_name' => 'PT Uji',
                'applicant_name' => 'Budi',
                'contact_email' => 'budi@uji.test',
                'contact_phone' => '0811',
                'form_version' => $this->scheme()->form_version,
            ]
        );
    }

    /**
     * Mengisi seluruh field wajib dengan nilai yang masuk akal per tipe, supaya
     * submit sampai ke pemeriksaan dokumen dan bukan berhenti di validasi form.
     */
    private function fillRequiredFields(CertificationApplication $application): void
    {
        $forms = app(\App\Services\DynamicFormService::class);
        $scheme = $forms->schemeForApplication($application);
        $values = [];

        foreach ($forms->visibleFields($scheme, [])->where('is_required', true) as $field) {
            $firstOption = $field->options->first()?->value;

            $values[$field->code] = match ($field->type) {
                'select', 'radio' => $firstOption ?? 'Uji',
                'checkbox_group', 'multiselect' => [$firstOption ?? 'Uji'],
                'boolean' => 'yes',
                'number', 'currency' => 1,
                'date' => now()->toDateString(),
                'email' => 'uji@contoh.test',
                'url' => 'https://contoh.test',
                'file' => ['path' => 'uji/berkas.pdf', 'original_name' => 'berkas.pdf'],
                default => 'Uji',
            };
        }

        app(ApplicationSubmissionService::class)->saveValues($application, $values, $application->client_id);
    }

    public function test_seeder_menanam_tiga_template_iso_9001(): void
    {
        $this->seedAll();

        $codes = GisFormTemplate::where('certification_scheme_id', $this->scheme()->id)
            ->pluck('code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['FrM.9100', 'FrM.9102', 'FrM.9104'], $codes);
        $this->assertDatabaseHas('scheme_required_documents', [
            'code' => 'cooperation_agreement',
            'document_group' => 'gis_form',
        ]);
    }

    public function test_klien_melihat_blok_terkunci_dan_tombol_minta_template(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $application = $this->draftFor($client);

        $this->actingAs($client)
            ->get(route('client.applications.edit', $application))
            ->assertOk()
            ->assertSee('Form Wajib GIS')
            ->assertSee('Minta Template Formulir GIS')
            ->assertSee('Terkunci')
            // Daftar template belum boleh bocor sebelum permintaan disetujui.
            ->assertDontSee('Unduh Template');
    }

    public function test_unggahan_formulir_gis_ditolak_sebelum_template_dibagikan(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $application = $this->draftFor($client);

        $this->actingAs($client)
            ->post(route('client.documents.store', $application), [
                'document_code' => 'cooperation_agreement',
                'file' => UploadedFile::fake()->create('pks.pdf', 40, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('application_documents', [
            'application_id' => $application->id,
            'document_code' => 'cooperation_agreement',
        ]);
    }

    public function test_dokumen_perusahaan_tetap_bisa_diunggah_sebelum_template_dibagikan(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $application = $this->draftFor($client);

        $this->actingAs($client)
            ->post(route('client.documents.store', $application), [
                'document_code' => 'npwp',
                'file' => UploadedFile::fake()->create('npwp.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'document_code' => 'npwp',
        ]);
    }

    public function test_klien_mengirim_permintaan_dan_admin_melihatnya(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $admin = $this->user('admin_application');
        $application = $this->draftFor($client);

        $this->actingAs($client)
            ->post(route('client.gis-form-requests.store', $application), ['client_note' => 'Mohon dikirim.'])
            ->assertRedirect();

        $this->assertDatabaseHas('gis_form_requests', [
            'application_id' => $application->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('internal.gis-form-requests.index'))
            ->assertOk()
            ->assertSee('Mohon dikirim.')
            ->assertSee('Setujui');
    }

    public function test_permintaan_ganda_ditolak_selama_masih_menunggu(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $application = $this->draftFor($client);

        $this->actingAs($client)->post(route('client.gis-form-requests.store', $application));

        $this->actingAs($client)
            ->post(route('client.gis-form-requests.store', $application))
            ->assertSessionHasErrors('gis_form');

        $this->assertSame(1, GisFormRequest::where('application_id', $application->id)->count());
    }

    public function test_setelah_disetujui_klien_dapat_mengunduh_template_dan_mengunggah_formulir(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $admin = $this->user('admin_application');
        $application = $this->draftFor($client);

        $this->actingAs($client)->post(route('client.gis-form-requests.store', $application));
        $gisFormRequest = GisFormRequest::where('application_id', $application->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('internal.gis-form-requests.approve', $gisFormRequest), ['response_note' => 'Silakan diisi.'])
            ->assertRedirect();

        $this->assertSame('approved', $gisFormRequest->fresh()->status);

        $template = GisFormTemplate::where('code', 'FrM.9102')->firstOrFail();

        $this->actingAs($client)
            ->get(route('secure-files.gis-form-template', $template))
            ->assertOk();

        $this->actingAs($client)
            ->post(route('client.documents.store', $application), [
                'document_code' => 'cooperation_agreement',
                'file' => UploadedFile::fake()->create('pks.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'document_code' => 'cooperation_agreement',
        ]);

        $this->actingAs($client)
            ->get(route('client.applications.edit', $application))
            ->assertOk()
            ->assertSee('Unduh Template');
    }

    public function test_klien_lain_tidak_dapat_mengunduh_template(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $other = $this->user('client');
        $admin = $this->user('admin_application');
        $application = $this->draftFor($client);

        $this->actingAs($client)->post(route('client.gis-form-requests.store', $application));
        $gisFormRequest = GisFormRequest::where('application_id', $application->id)->firstOrFail();
        $this->actingAs($admin)->post(route('internal.gis-form-requests.approve', $gisFormRequest));

        $template = GisFormTemplate::where('code', 'FrM.9100')->firstOrFail();

        $this->actingAs($other)
            ->get(route('secure-files.gis-form-template', $template))
            ->assertForbidden();
    }

    public function test_penolakan_permintaan_mengirim_alasan_ke_klien(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $admin = $this->user('admin_application');
        $application = $this->draftFor($client);

        $this->actingAs($client)->post(route('client.gis-form-requests.store', $application));
        $gisFormRequest = GisFormRequest::where('application_id', $application->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('internal.gis-form-requests.reject', $gisFormRequest), ['response_note' => 'Lengkapi data perusahaan dulu.'])
            ->assertRedirect();

        $this->actingAs($client)
            ->get(route('client.applications.edit', $application))
            ->assertOk()
            ->assertSee('Lengkapi data perusahaan dulu.')
            // Setelah ditolak klien boleh mengajukan ulang.
            ->assertSee('Minta Template Formulir GIS');
    }

    public function test_submit_diblokir_dengan_pesan_template_belum_dibagikan(): void
    {
        $this->seedAll();
        $client = $this->user('client');
        $application = $this->draftFor($client);
        $this->fillRequiredFields($application);

        $this->actingAs($client)
            ->post(route('client.applications.submit', $application))
            ->assertSessionHasErrors('documents');

        $this->assertStringContainsString(
            'Template Formulir Wajib GIS belum dibagikan',
            session('errors')->first('documents')
        );
    }

    public function test_superadmin_mengunggah_template_menaikkan_versi(): void
    {
        $this->seedAll();
        $superadmin = $this->user('superadmin');
        $scheme = $this->scheme();

        $this->actingAs($superadmin)
            ->post(route('superadmin.gis-forms.store', $scheme), [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'file' => UploadedFile::fake()->create('frm9100.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect();

        $template = GisFormTemplate::where('code', 'FrM.9100')->firstOrFail();
        $this->assertSame(2, $template->version);
        $this->assertSame('pdf', $template->extension);
    }

    public function test_klien_tidak_boleh_membuka_kelola_template(): void
    {
        $this->seedAll();
        $client = $this->user('client');

        $this->actingAs($client)
            ->get(route('superadmin.gis-forms.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('internal.gis-form-requests.index'))
            ->assertForbidden();
    }
}
