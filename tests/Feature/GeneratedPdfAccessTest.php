<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GeneratedPdf;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Route unduh PDF hasil generate tidak lagi berada di dalam grup peran, jadi
 * satu-satunya yang menjaganya adalah pemeriksaan di dalam controller. Test ini
 * yang memastikan pemeriksaan itu tidak hilang tanpa sengaja.
 */
class GeneratedPdfAccessTest extends TestCase
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

    private function pdfFor(User $client, string $documentType = 'review'): GeneratedPdf
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji Unduh',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'PDF-'.Str::random(6),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        $path = 'generated/reviews/'.$application->id.'/contoh.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 contoh');

        return GeneratedPdf::create([
            'application_id' => $application->id,
            'document_type' => $documentType,
            'template_code' => 'lssm',
            'document_version' => 1,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', '%PDF-1.4 contoh'),
            'source_snapshot' => [],
        ]);
    }

    public function test_admin_dan_teknis_dapat_mengunduh_pdf(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $pdf = $this->pdfFor($this->user('client'));

        $this->actingAs($this->user('admin_application'))
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertOk();

        $this->actingAs($this->user('technical'))
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertOk();

        $this->actingAs($this->user('superadmin'))
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertOk();
    }

    public function test_finance_client_dan_auditor_ditolak(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $client = $this->user('client');
        $pdf = $this->pdfFor($client);

        $this->actingAs($this->user('finance'))
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertForbidden();

        $this->actingAs($this->user('auditor'))
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('internal.generated-pdf.download', $pdf))
            ->assertForbidden();
    }

    public function test_nama_berkas_membedakan_surat_tugas_dari_tinjauan(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $client = $this->user('client');
        $technical = $this->user('technical');

        $review = $this->pdfFor($client, 'review');
        $response = $this->actingAs($technical)
            ->get(route('internal.generated-pdf.download', $review))
            ->assertOk();
        $this->assertStringContainsString('Tinjauan-Permohonan', $response->headers->get('content-disposition'));

        $letter = $this->pdfFor($client, 'assignment_letter');
        $response = $this->actingAs($technical)
            ->get(route('internal.generated-pdf.download', $letter))
            ->assertOk();
        $this->assertStringContainsString('Surat-Tugas', $response->headers->get('content-disposition'));
    }
}
