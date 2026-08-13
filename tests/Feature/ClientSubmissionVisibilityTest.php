<?php

namespace Tests\Feature;

use App\Models\ApplicationDocument;
use App\Models\ApplicationValue;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tim Teknis memegang keputusan setuju/tolak, jadi halaman tinjauan teknis
 * wajib memperlihatkan seluruh isian dan berkas klien — bukan hanya subset
 * dokumen teknis seperti sebelumnya.
 */
class ClientSubmissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_halaman_tinjauan_teknis_memuat_isian_dan_dokumen_klien(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);

        $client = $this->user('client');
        $tech = $this->user('technical');
        $scheme = CertificationScheme::with(['sections.fields', 'requiredDocuments'])
            ->where('code', 'ISO9001')
            ->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'technical_review',
            'current_step' => 'technical_review',
            'company_name' => 'PT Konteks Penuh',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'VIS-'.Str::random(4),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        // Satu isian teks pada bagian pertama, supaya labelnya wajib tampil.
        $field = $scheme->sections->first()->fields->firstWhere('type', 'text');
        ApplicationValue::create([
            'application_id' => $application->id,
            'field_code' => $field->code,
            'value_text' => 'Nilai Uji Tampil',
        ]);

        // Satu dokumen administrasi bersama versi berkasnya.
        $required = $scheme->requiredDocuments->firstOrFail();
        $document = ApplicationDocument::create([
            'application_id' => $application->id,
            'scheme_required_document_id' => $required->id,
            'document_code' => $required->code,
            'document_name' => $required->name,
        ]);
        $document->versions()->create([
            'version' => 1,
            'original_name' => 'berkas-klien-uji.pdf',
            'stored_name' => 'berkas-klien-uji.pdf',
            'file_path' => 'applications/'.$application->id.'/documents/berkas-klien-uji.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'is_current' => true,
            'uploaded_by' => $client->id,
        ]);

        $html = $this->actingAs($tech)
            ->get(route('technical.reviews.show', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Data Form Klien', $html);
        $this->assertStringContainsString($field->label, $html);
        $this->assertStringContainsString('Nilai Uji Tampil', $html);

        $this->assertStringContainsString('Dokumen Unggahan Klien', $html);
        $this->assertStringContainsString('berkas-klien-uji.pdf', $html);
        $this->assertStringContainsString(
            route('secure-files.application-document', $document),
            $html,
            'Tautan unduh dokumen klien tidak tersedia untuk Tim Teknis.'
        );
    }
}
