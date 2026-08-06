<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\ReviewFormItem;
use App\Models\Role;
use App\Models\User;
use App\Services\ReviewPdfService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Perilaku formulir FrM.9107/GIS: hasil kajian dan keterangan berupa pilihan,
 * plus kesimpulan teknis yang dicetak dengan coretan pada PDF.
 */
class Frm9107ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
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

    private function applicationInReview(): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'FRM-9107-1',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    public function test_jumlah_baris_sesuai_formulir_frm_9107(): void
    {
        $this->seedAll();
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $application = $this->applicationInReview();
        $reviews = app(\App\Services\ReviewService::class);

        // Checklist kelengkapan klien: 16 item (tanpa Tinjauan Permohonan yang diisi LS).
        $this->assertSame(16, $scheme->requiredDocuments()->count());
        $this->assertSame(3, $scheme->requiredDocuments()->where('document_group', 'gis_form')->count());

        // FrM.9107: 7 baris administrasi + 7 baris teknis.
        $this->assertCount(7, $reviews->formRows($application, 'administration'));
        $this->assertCount(7, $reviews->formRows($application, 'technical'));
    }

    public function test_admin_menyimpan_hasil_kajian_dan_keterangan_bertanggal(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)
            ->post(route('internal.applications.review', $application), [
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Helvina',
                'site_count' => 3,
                'items' => [
                    ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient',
                        'remark_option' => 'tgl_berlaku', 'remark_date' => '2030-03-18'],
                    ['type' => 'document', 'code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan',
                        'status' => 'insufficient', 'remark_option' => 'belum_sesuai'],
                ],
            ])
            ->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)->firstOrFail();
        $this->assertSame(3, $review->site_count);

        $nib = ReviewFormItem::where('application_review_id', $review->id)->where('item_code', 'nib')->firstOrFail();
        $this->assertSame('tgl_berlaku', $nib->remark_option);
        $this->assertSame('2030-03-18', $nib->remark_date->format('Y-m-d'));

        $deed = ReviewFormItem::where('application_review_id', $review->id)->where('item_code', 'company_deed')->firstOrFail();
        $this->assertSame('belum_sesuai', $deed->remark_option);
        // Tanggal tidak boleh ikut tersimpan bila opsinya bukan Tgl Berlaku.
        $this->assertNull($deed->remark_date);
    }

    public function test_keterangan_tanggal_berlaku_wajib_disertai_tanggal(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)
            ->post(route('internal.applications.review', $application), [
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Helvina',
                'items' => [
                    ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient',
                        'remark_option' => 'tgl_berlaku'],
                ],
            ])
            ->assertSessionHasErrors('items.0.remark_date');
    }

    /**
     * @return array{0: CertificationApplication, 1: User, 2: User}
     */
    private function forwardToTechnical(): array
    {
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Helvina',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $application))->assertRedirect();

        return [$application->refresh(), $admin, $technical];
    }

    public function test_teknis_menyimpan_pilihan_kesimpulan_dan_panelis(): void
    {
        $this->seedAll();
        [$application, , $technical] = $this->forwardToTechnical();
        $panelist = $this->user('technical');

        $this->actingAs($technical)
            ->post(route('technical.reviews.save', $application), [
                'action_date' => now()->format('Y-m-d'),
                'scope_conformity' => 'sesuai',
                'audit_capability_choice' => 'dapat',
                'panelist_ids' => [$panelist->id],
            ])
            ->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)
            ->where('review_type', 'technical')
            ->firstOrFail();

        $this->assertSame('sesuai', $review->scope_conformity);
        $this->assertSame('dapat', $review->audit_capability_choice);
        $this->assertSame([$panelist->id], $review->panelist_ids);
    }

    public function test_panelis_harus_akun_berperan_panelis(): void
    {
        $this->seedAll();
        [$application, , $technical] = $this->forwardToTechnical();
        $client = $this->user('client');

        $this->actingAs($technical)
            ->post(route('technical.reviews.save', $application), [
                'action_date' => now()->format('Y-m-d'),
                'panelist_ids' => [$client->id],
            ])
            ->assertStatus(422);
    }

    public function test_tim_auditor_pada_pdf_diambil_dari_penugasan_auditor(): void
    {
        $this->seedAll();
        \Illuminate\Support\Facades\Storage::fake('private');
        [$application, $admin, $technical] = $this->forwardToTechnical();
        $auditor = $this->user('auditor');
        $panelist = $this->user('technical');

        \App\Models\AuditAssignment::create([
            'application_id' => $application->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        $this->actingAs($technical)->post(route('technical.reviews.save', $application), [
            'action_date' => now()->format('Y-m-d'),
            'panelist_ids' => [$panelist->id],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);

        $this->assertSame(['1. '.$auditor->name.' (LA)'], array_map(
            fn (string $name, int $index) => ($index + 1).'. '.$name,
            $record->source_snapshot['assigned_auditors'],
            array_keys($record->source_snapshot['assigned_auditors'])
        ));
        $this->assertSame([$panelist->name], $record->source_snapshot['assigned_panelists']);
    }

    public function test_baris_mandays_tidak_menawarkan_pilihan(): void
    {
        $this->seedAll();
        \Illuminate\Support\Facades\Storage::fake('private');
        [$application, $admin, $technical] = $this->forwardToTechnical();

        $this->actingAs($technical)->post(route('technical.reviews.save', $application), [
            'action_date' => now()->format('Y-m-d'),
            'aspects' => ['audit_mandays' => '6'],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = \Illuminate\Support\Facades\Storage::disk('private')->get($record->file_path);

        // Tanda kurung diescape pada stream PDF, jadi yang dicek bagian sebelumnya.
        $this->assertStringContainsString('FrM.9105 / GIS', $raw);
        // Angka mandays tidak boleh muncul sebagai pilihan di baris tersebut.
        $this->assertStringNotContainsString('6 mandays', $raw);
    }

    public function test_kajian_administrasi_tidak_menghapus_kesimpulan_teknis(): void
    {
        $this->seedAll();
        [$application, $admin, $technical] = $this->forwardToTechnical();

        $this->actingAs($technical)->post(route('technical.reviews.save', $application), [
            'action_date' => now()->format('Y-m-d'),
            'scope_conformity' => 'sesuai',
            'audit_capability_choice' => 'dapat',
        ])->assertRedirect();

        $this->actingAs($technical)->post(route('technical.reviews.complete', $application))->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.review', $application->refresh()), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Helvina',
        ])->assertRedirect();

        $technicalReview = ApplicationReview::where('application_id', $application->id)
            ->where('review_type', 'technical')
            ->firstOrFail();

        $this->assertSame('sesuai', $technicalReview->scope_conformity);
        $this->assertSame('dapat', $technicalReview->audit_capability_choice);
    }

    public function test_pdf_memakai_kode_frm_9107_dan_mencetak_pilihan(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Helvina',
            'items' => [
                ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient',
                    'remark_option' => 'tgl_berlaku', 'remark_date' => '2030-03-18'],
            ],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        $this->assertStringContainsString('FrM.9107/GIS', $raw);
        $this->assertStringContainsString('coret yang tidak perlu', $raw);
        $this->assertStringContainsString('Belum Cukup', $raw);
        // Tanggal berlaku ditulis bergaya formulir GIS.
        $this->assertStringContainsString('18 Maret 2030', $raw);

        $snapshot = $record->source_snapshot;
        $nib = collect($snapshot['documents'])->firstWhere('code', 'nib');
        $this->assertSame('tgl_berlaku', $nib['remark_option']);
        $this->assertSame('18 Maret 2030', $nib['remark_date']);
    }

    public function test_nama_penanda_tangan_diambil_dari_akun_bukan_isian(): void
    {
        $this->seedAll();
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            // Nama karangan tidak boleh dipakai formulir.
            'signed_name' => 'Nama Karangan',
        ])->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)->firstOrFail();
        $this->assertSame($admin->name, $review->signed_name);
    }

    public function test_tanggal_tinjauan_tidak_mundur_sehari_pada_pdf(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => '2026-08-05',
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);

        $this->assertStringContainsString('5 Agustus 2026', Storage::disk('private')->get($record->file_path));
    }

    public function test_baris_bertanggal_berlaku_tidak_mencetak_sesuai(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'items' => [
                ['type' => 'document', 'code' => 'nib', 'label' => 'NIB', 'status' => 'sufficient',
                    'remark_option' => 'tgl_berlaku', 'remark_date' => '2030-03-18'],
            ],
        ])->assertRedirect();

        $record = app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);
        $raw = Storage::disk('private')->get($record->file_path);

        /*
         * Baris NIB memakai masa berlaku, jadi hanya tanggalnya yang tercetak.
         * "Sesuai" tetap muncul untuk baris lain, sehingga yang diperiksa adalah
         * urutan teks pada baris pertama tabel.
         */
        // Label baris memakai teks formulir ("NIB"), bukan nama dokumen checklist.
        $nibPosition = strpos($raw, '(NIB) Tj');
        $datePosition = strpos($raw, '18 Maret 2030');
        $this->assertNotFalse($nibPosition);
        $this->assertNotFalse($datePosition);
        $this->assertStringNotContainsString('Sesuai', substr($raw, $nibPosition, $datePosition - $nibPosition));
    }

    public function test_tanda_tangan_dicatat_saat_pdf_dibuat(): void
    {
        $this->seedAll();
        Storage::fake('private');
        $admin = $this->user('admin_application');
        $admin->forceFill(['signature_path' => 'signatures/admin.jpg'])->save();
        $application = $this->applicationInReview();

        $this->actingAs($admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Helvina',
        ])->assertRedirect();

        app(ReviewPdfService::class)->generate($application->refresh(), $admin->id);

        $review = ApplicationReview::where('application_id', $application->id)->firstOrFail();
        $this->assertSame('signatures/admin.jpg', $review->signature_path);
    }
}
