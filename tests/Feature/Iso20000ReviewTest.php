<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GisFormTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\ReviewPdfService;
use App\Services\ReviewService;
use Database\Seeders\GisFormTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Skema ISO/IEC 20000-1 memakai formulir tinjauan FrM.9112/GIS. Tata letaknya
 * sama dengan FrM.9107, yang berbeda hanya judul kop, kode formulir, lembaga
 * yang disebut pada kesimpulan (LSSMLTI), dan formulir mandays (FrM.9113).
 */
class Iso20000ReviewTest extends TestCase
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
            'email' => $roleCode.Str::random(5).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function scheme(): CertificationScheme
    {
        return CertificationScheme::where('code', 'ISO20000')->firstOrFail();
    }

    private function application(): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $this->scheme()->id,
            'form_version' => $this->scheme()->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji Layanan TI',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'LTI-001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_checklist_dan_template_gis_terpasang(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();

        $this->assertSame(16, $scheme->requiredDocuments()->count());
        $this->assertSame(3, $scheme->requiredDocuments()->where('document_group', 'gis_form')->count());

        $codes = GisFormTemplate::where('certification_scheme_id', $scheme->id)->pluck('code')->sort()->values()->all();
        $this->assertSame(['FrM.9100', 'FrM.9102', 'FrM.9104'], $codes);

        // Dokumen khas layanan TI ikut masuk checklist.
        foreach (['sla_document', 'service_catalog'] as $code) {
            $this->assertTrue($scheme->requiredDocuments()->where('code', $code)->exists(), $code.' tidak ada di checklist.');
        }
    }

    public function test_jumlah_baris_sesuai_formulir_frm_9112(): void
    {
        $this->seedAll();
        $reviews = app(ReviewService::class);
        $application = $this->application();

        // FrM.9112: 7 baris administrasi + 8 baris teknis.
        $this->assertCount(7, $reviews->formRows($application, 'administration'));
        $this->assertCount(8, $reviews->formRows($application, 'technical'));
    }

    public function test_pdf_memakai_kop_dan_kode_frm_9112(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->application();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        $this->assertStringContainsString('TINJAUAN PERMOHONAN SERTIFIKASI LSSMLTI', $raw);
        $this->assertStringContainsString('FrM.9112/GIS-0', $raw);
        // Mandays memakai formulir lampiran khusus skema ini.
        $this->assertStringContainsString('FrM.9113 / GIS', $raw);
        $this->assertStringNotContainsString('FrM.9105 / GIS', $raw);
        // Baris kesimpulan menyebut lembaga yang benar.
        $this->assertStringContainsString('ruang lingkup LSSMLTI', $raw);
    }

    public function test_skema_lain_tetap_memakai_kop_dan_mandays_sendiri(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji Mutu',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'LSSM-CEK',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        $this->assertStringContainsString('FrM.9107/GIS', $raw);
        $this->assertStringContainsString('FrM.9105 / GIS', $raw);
        $this->assertStringNotContainsString('LSSMLTI', $raw);
    }
}
