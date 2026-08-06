<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GisFormTemplate;
use App\Models\ReviewFormItem;
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
 * Skema ISO/IEC 27001 memakai FrM.9101/GIS-7 (LSSMKI). Bedanya dengan formulir
 * lain: tabel administrasinya memuat enam baris penilaian peninjau yang bukan
 * dokumen unggahan klien, dengan keterangan berupa teks bebas.
 */
class Iso27001ReviewTest extends TestCase
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
        return CertificationScheme::where('code', 'ISO27001')->firstOrFail();
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
            'company_name' => 'PT Uji Keamanan Informasi',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'SMKI-001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_checklist_dan_template_gis_terpasang(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();

        $this->assertSame(19, $scheme->requiredDocuments()->count());
        $this->assertSame(3, $scheme->requiredDocuments()->where('document_group', 'gis_form')->count());

        $codes = GisFormTemplate::where('certification_scheme_id', $scheme->id)->pluck('code')->sort()->values()->all();
        $this->assertSame(['FrM.9100', 'FrM.9102', 'FrM.9104'], $codes);

        // Dokumen khas keamanan informasi ikut masuk checklist.
        foreach (['soa', 'risk_methodology', 'information_security_policy', 'security_objectives'] as $code) {
            $this->assertTrue($scheme->requiredDocuments()->where('code', $code)->exists(), $code.' tidak ada di checklist.');
        }

        // Dokumen data center bersifat "jika ada".
        $dataCenter = $scheme->requiredDocuments()->where('code', 'data_center_document')->firstOrFail();
        $this->assertSame('conditional', $dataCenter->requirement);
    }

    public function test_jumlah_baris_sesuai_formulir_frm_9101_lssmki(): void
    {
        $this->seedAll();
        $reviews = app(ReviewService::class);
        $application = $this->application();

        // 13 baris administrasi (7 dokumen + 6 penilaian) dan 12 baris teknis.
        $administration = $reviews->formRows($application, 'administration');
        $this->assertCount(13, $administration);
        $this->assertCount(12, $reviews->formRows($application, 'technical'));

        // Enam baris terakhir adalah penilaian, bukan dokumen unggahan.
        $assessments = $administration->reject->expects_document;
        $this->assertCount(6, $assessments);

        foreach ($assessments as $row) {
            $this->assertNull($row->document, $row->code.' seharusnya tidak terhubung ke dokumen.');
            $this->assertTrue($row->free_remark, $row->code.' seharusnya berketerangan teks bebas.');
        }
    }

    public function test_admin_menyimpan_penilaian_berketerangan_bebas(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $application = $this->application();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'items' => [
                ['type' => 'document', 'code' => 'it_infrastructure', 'label' => 'Infrastruktur IT',
                    'status' => 'sufficient', 'notes' => 'Dua data center, kompleksitas menengah.'],
            ],
        ])->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)->firstOrFail();
        $item = ReviewFormItem::where('application_review_id', $review->id)
            ->where('item_code', 'it_infrastructure')
            ->firstOrFail();

        $this->assertSame('Dua data center, kompleksitas menengah.', $item->notes);
        $this->assertNull($item->remark_option);
    }

    public function test_pdf_memakai_kop_lssmki_dan_mencetak_keterangan_bebas(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->application();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'items' => [
                ['type' => 'document', 'code' => 'business_ms_level', 'label' => 'Level MS',
                    'status' => 'sufficient', 'notes' => 'Level menengah'],
            ],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        $this->assertStringContainsString('TINJAUAN PERMOHONAN SERTIFIKASI LSSMKI', $raw);
        $this->assertStringContainsString('FrM.9101/GIS-7', $raw);
        $this->assertStringContainsString('FrM.9106 / GIS', $raw);
        // Keterangan baris penilaian dicetak apa adanya, tanpa pilihan bercoret.
        $this->assertStringContainsString('Level menengah', $raw);
        $this->assertStringNotContainsString('FrM.9105 / GIS', $raw);
    }
}
