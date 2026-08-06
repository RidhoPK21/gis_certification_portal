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
 * Skema ISO 22000 memakai formulir tinjauan FrM.9114/GIS yang dipakai bersama
 * LSSMKP dan LSHACCP. Tata letaknya sama dengan FrM.9107; yang berbeda judul kop,
 * kode formulir, lembaga pada kesimpulan, dan formulir mandays (FrM.9115).
 */
class Iso22000ReviewTest extends TestCase
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
        return CertificationScheme::where('code', 'ISO22000')->firstOrFail();
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
            'company_name' => 'PT Uji Pangan',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'SMKP-001',
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

        // Dokumen khas keamanan pangan ikut masuk checklist.
        $prp = $scheme->requiredDocuments()->where('code', 'prp_document')->firstOrFail();
        $this->assertSame('required', $prp->requirement);

        // Laporan insiden hanya bila ada, jadi bersifat kondisional.
        $incident = $scheme->requiredDocuments()->where('code', 'food_incident_report')->firstOrFail();
        $this->assertSame('conditional', $incident->requirement);
    }

    public function test_jumlah_baris_sesuai_formulir_frm_9114(): void
    {
        $this->seedAll();
        $reviews = app(ReviewService::class);
        $application = $this->application();

        // FrM.9114: 7 baris administrasi + 7 baris teknis.
        $this->assertCount(7, $reviews->formRows($application, 'administration'));
        $this->assertCount(7, $reviews->formRows($application, 'technical'));
    }

    public function test_pdf_memakai_kop_dan_kode_frm_9114(): void
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

        $this->assertStringContainsString('TINJAUAN PERMOHONAN SERTIFIKASI LSSMKP - LSHACCP', $raw);
        $this->assertStringContainsString('FrM.9114/GIS-0', $raw);
        $this->assertStringContainsString('FrM.9115 / GIS', $raw);
        // Label kesimpulan dibungkus per baris di dalam sel, jadi yang dicek katanya.
        $this->assertStringContainsString('LSSMKP', $raw);
        $this->assertStringContainsString('LSHACCP', $raw);
        // Tidak boleh tertukar dengan formulir skema lain.
        $this->assertStringNotContainsString('FrM.9105 / GIS', $raw);
        $this->assertStringNotContainsString('FrM.9113 / GIS', $raw);
    }
}
