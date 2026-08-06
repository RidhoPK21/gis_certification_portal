<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\SchemeField;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $user = User::create([
            'name' => 'Klien Uji',
            'email' => 'klien@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
            'company_name' => 'PT Uji',
            'phone' => '0811',
        ]);

        $user->roles()->attach(Role::where('code', 'client')->value('id'));

        return $user;
    }

    private function draftFor(User $client, CertificationScheme $scheme): CertificationApplication
    {
        return app(\App\Services\ApplicationSubmissionService::class)->createDraft(
            $client->id,
            $scheme->id,
            [
                'company_name' => 'PT Uji',
                'applicant_name' => 'Budi',
                'contact_email' => 'budi@uji.test',
                'contact_phone' => '0811',
                'form_version' => $scheme->form_version,
            ]
        );
    }

    public function test_klien_dapat_membuka_pilihan_skema(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.applications.schemes'))
            ->assertOk()
            ->assertSee('Pilih Skema Sertifikasi');
    }

    public function test_klien_dapat_membuat_draft(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::where('is_active', true)->orderBy('sort_order')->firstOrFail();

        $this->actingAs($client)
            ->post(route('client.applications.store', $scheme), [
                'company_name' => 'PT Uji',
                'applicant_name' => 'Budi',
                'contact_email' => 'budi@uji.test',
                'contact_phone' => '0811',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'status' => 'draft',
        ]);
    }

    public function test_klien_dapat_menyimpan_nilai_field(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);
        $field = SchemeField::whereHas('section', fn ($q) => $q->where('certification_scheme_id', $scheme->id))->firstOrFail();

        $this->actingAs($client)
            ->put(route('client.applications.update', $app), [
                'fields' => [$field->code => 'Nilai Uji'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id,
            'field_code' => $field->code,
            'value_text' => 'Nilai Uji',
        ]);
    }

    public function test_submit_menghasilkan_nomor_order_dan_status_admin_review(): void
    {
        Storage::fake('private');
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);

        // Isi semua field wajib pada snapshot.
        $forms = app(\App\Services\DynamicFormService::class);
        $schemeSnapshot = $forms->schemeForApplication($app);
        $requiredFields = $schemeSnapshot->sections->flatMap->fields->where('is_required', true);
        $values = [];
        foreach ($requiredFields as $field) {
            $values[$field->code] = match ($field->type) {
                'select', 'radio' => $field->options->first()->value ?? 'x',
                'boolean' => 'yes',
                'email' => 'kontak@uji.test',
                'number' => '10',
                'date' => '2026-01-01',
                'url' => 'https://uji.test',
                'checkbox_group' => [$field->options->first()->value ?? 'x'],
                default => 'Isian uji',
            };
        }
        app(\App\Services\ApplicationSubmissionService::class)->saveValues($app, $values, $client->id);

        /*
         * Skema ini memakai Formulir Wajib GIS, jadi slot unggahannya baru
         * terbuka setelah Admin Permohonan menyetujui permintaan template.
         */
        $this->actingAs($client)->post(route('client.gis-form-requests.store', $app));
        $admin = User::create([
            'name' => 'Admin Permohonan', 'email' => 'admin.permohonan@example.com',
            'password' => 'RahasiaKuat123', 'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('code', 'admin_application')->value('id'));
        $this->actingAs($admin)->post(route(
            'internal.gis-form-requests.approve',
            \App\Models\GisFormRequest::where('application_id', $app->id)->firstOrFail()
        ));

        // Unggah semua dokumen wajib.
        $requiredDocs = $forms->applicableDocuments($schemeSnapshot, $values)->where('requirement', 'required');
        foreach ($requiredDocs as $doc) {
            $this->actingAs($client)->post(route('client.documents.store', $app), [
                'document_code' => $doc->code,
                'file' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
            ]);
        }

        $this->actingAs($client)
            ->post(route('client.applications.submit', $app))
            ->assertRedirect(route('client.applications.show', $app));

        $app->refresh();
        $this->assertNotNull($app->order_number);
        $this->assertSame('admin_review', $app->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'type' => 'application_submitted']);
    }

    public function test_klien_tidak_bisa_mengakses_permohonan_milik_orang_lain(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);

        $lain = User::create([
            'name' => 'Klien Lain',
            'email' => 'lain@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $lain->roles()->attach(Role::where('code', 'client')->value('id'));

        $this->actingAs($lain)
            ->get(route('client.applications.show', $app))
            ->assertForbidden();
    }

    public function test_klien_dapat_menghapus_draft_miliknya(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);

        $this->actingAs($client)
            ->delete(route('client.applications.destroy', $app))
            ->assertRedirect(route('client.applications.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('applications', ['id' => $app->id]);

        // Data anak ikut terhapus lewat cascade FK.
        $this->assertDatabaseMissing('application_values', ['application_id' => $app->id]);
        $this->assertDatabaseMissing('application_status_history', ['application_id' => $app->id]);
    }

    public function test_menghapus_draft_ikut_menghapus_file_dokumen(): void
    {
        Storage::fake('private');
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);

        $forms = app(\App\Services\DynamicFormService::class);

        // Formulir Wajib GIS terkunci sampai templatenya dibagikan.
        $doc = $forms->applicableDocuments($forms->schemeForApplication($app), [])
            ->firstWhere(fn ($document) => ($document->document_group ?? 'company') !== 'gis_form');

        $this->actingAs($client)->post(route('client.documents.store', $app), [
            'document_code' => $doc->code,
            'file' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ]);

        $path = $app->documents()->firstOrFail()->currentVersion->file_path;
        Storage::disk('private')->assertExists($path);

        $this->actingAs($client)
            ->delete(route('client.applications.destroy', $app))
            ->assertRedirect(route('client.applications.index'));

        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseMissing('application_documents', ['application_id' => $app->id]);
    }

    public function test_klien_lain_tidak_bisa_menghapus_draft(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->draftFor($client, $scheme);

        $lain = User::create([
            'name' => 'Klien Lain',
            'email' => 'lain@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $lain->roles()->attach(Role::where('code', 'client')->value('id'));

        $this->actingAs($lain)
            ->delete(route('client.applications.destroy', $app))
            ->assertForbidden();

        $this->assertDatabaseHas('applications', ['id' => $app->id]);
    }

    /*
     * Status revisi tetap bisa diedit klien, tapi ordernya sudah berjalan
     * di tim GIS sehingga tidak boleh dihapus.
     */
    public function test_permohonan_yang_sudah_dikirim_tidak_bisa_dihapus(): void
    {
        $client = $this->client();
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        foreach (['admin_review', 'client_revision', 'revision_requested'] as $status) {
            $app = $this->draftFor($client, $scheme);
            $app->update(['status' => $status, 'order_number' => 'UJI/' . $status]);

            $this->actingAs($client)
                ->delete(route('client.applications.destroy', $app))
                ->assertForbidden();

            $this->assertDatabaseHas('applications', ['id' => $app->id]);
        }
    }
}
