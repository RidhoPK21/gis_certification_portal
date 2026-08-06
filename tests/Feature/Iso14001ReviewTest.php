<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
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
 * Skema ISO 14001 memakai formulir tinjauan FrM.9101/GIS yang berbeda dari
 * FrM.9107: identitasnya memakai NACE Code, sebagian dokumen dikaji dua tahap,
 * dan bagian teknisnya memuat tabel kompetensi spesifik auditor 6.1-6.7.
 */
class Iso14001ReviewTest extends TestCase
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
        return CertificationScheme::where('code', 'ISO14001')->firstOrFail();
    }

    private function application(string $status = 'admin_review'): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $this->scheme()->id,
            'form_version' => $this->scheme()->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Uji Lingkungan',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'LSML-001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_skema_iso_14001_terpasang_beserta_templatenya(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();

        $this->assertSame('lsml', $scheme->review_template);
        $this->assertSame('LSML-GIS', $scheme->order_prefix);
        $this->assertSame(22, $scheme->requiredDocuments()->count());
        $this->assertSame(3, $scheme->requiredDocuments()->where('document_group', 'gis_form')->count());

        // Risk management file ada di formulir teknis, tetapi opsional bagi klien.
        $riskFile = $scheme->requiredDocuments()->where('code', 'risk_management_file')->firstOrFail();
        $this->assertSame('optional', $riskFile->requirement);
        $this->assertSame('technical', $riskFile->review_group);

        $codes = GisFormTemplate::where('certification_scheme_id', $scheme->id)->pluck('code')->sort()->values()->all();
        $this->assertSame(['Fr.7202', 'FrM.9100', 'FrM.9104'], $codes);

        // FrM.9101: 11 baris administrasi + 15 baris teknis.
        $reviews = app(ReviewService::class);
        $application = $this->application();
        $this->assertCount(11, $reviews->formRows($application, 'administration'));
        $this->assertCount(15, $reviews->formRows($application, 'technical'));
    }

    public function test_dokumen_bergrup_both_dikaji_admin_dan_teknis(): void
    {
        $this->seedAll();
        $shared = $this->scheme()->requiredDocuments()->where('review_group', 'both')->pluck('code');

        $this->assertNotEmpty($shared);

        foreach ($shared as $code) {
            $document = $this->scheme()->requiredDocuments()->where('code', $code)->first();
            $this->assertTrue(ReviewService::documentInGroup($document, 'administration'));
            $this->assertTrue(ReviewService::documentInGroup($document, 'technical'));
        }
    }

    public function test_hasil_kajian_dua_tahap_tidak_saling_menimpa(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
        $application = $this->application();

        // Dokumen bergrup 'both' pada checklist ISO 14001.
        $code = 'master_document_list';

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'items' => [
                ['type' => 'document', 'code' => $code, 'label' => 'Daftar Induk Dokumen',
                    'status' => 'sufficient', 'remark_option' => 'sesuai'],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $application))->assertRedirect();

        $this->actingAs($technical)->post(route('technical.reviews.save', $application->refresh()), [
            'action_date' => now()->format('Y-m-d'),
            'items' => [
                ['type' => 'document', 'code' => $code, 'label' => 'Daftar Induk Dokumen',
                    'status' => 'insufficient', 'remark_option' => 'belum_sesuai'],
            ],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $document = collect($record->source_snapshot['documents'])->firstWhere('code', $code);

        $this->assertSame('sufficient', $document['stages']['administration']['review_status']);
        $this->assertSame('insufficient', $document['stages']['technical']['review_status']);
    }

    public function test_teknis_mencentang_kompetensi_spesifik_auditor(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
        $application = $this->application();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $application))->assertRedirect();

        // Kolom centang hanya ditawarkan pada skema bertemplat lsml.
        $this->actingAs($technical)
            ->get(route('technical.reviews.show', $application->refresh()))
            ->assertOk()
            ->assertSee('Kompetensi spesifik auditor')
            ->assertSee('value="6.4"', false);

        $this->actingAs($technical)->post(route('technical.reviews.save', $application), [
            'action_date' => now()->format('Y-m-d'),
            'auditor_competence_codes' => ['6.1', '6.3', '6.6'],
        ])->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)
            ->where('review_type', 'technical')
            ->firstOrFail();

        $this->assertSame(['6.1', '6.3', '6.6'], $review->auditor_competence_codes);
    }

    public function test_kode_kompetensi_di_luar_daftar_ditolak(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
        $application = $this->application();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $application))->assertRedirect();

        $this->actingAs($technical)
            ->post(route('technical.reviews.save', $application->refresh()), [
                'action_date' => now()->format('Y-m-d'),
                'auditor_competence_codes' => ['6.9'],
            ])
            ->assertSessionHasErrors('auditor_competence_codes.0');
    }

    public function test_pdf_memakai_formulir_frm_9101_dan_nace_code(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->application();

        $application->values()->create([
            'field_code' => 'nace_code',
            'value_text' => 'NACE 20.13',
            'field_label_snapshot' => 'NACE Code',
            'field_type_snapshot' => 'text',
        ]);

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        $this->assertStringContainsString('FrM.9101/GIS-5', $raw);
        $this->assertStringContainsString('NACE Code', $raw);
        $this->assertStringContainsString('NACE 20.13', $raw);
        $this->assertStringContainsString('Kompetensi spesifik auditor', $raw);
        // Blok identitas FrM.9101 tidak memuat baris Area Audit seperti FrM.9107.
        $this->assertStringNotContainsString('Area Audit', $raw);
    }

    public function test_skema_lssm_tidak_menampilkan_kompetensi_lingkungan(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
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
            'order_number' => 'LSSM-001',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $application))->assertRedirect();

        $this->actingAs($technical)
            ->get(route('technical.reviews.show', $application->refresh()))
            ->assertOk()
            ->assertDontSee('Kompetensi spesifik auditor');
    }
}
