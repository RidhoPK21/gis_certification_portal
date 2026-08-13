<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
use App\Models\AssignmentLetter;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GeneratedPdf;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Halaman Penugasan & Surat Tugas tetap hidup selama audit berjalan.
 *
 * Bukan hanya jendela sesaat sebelum surat terbit: Tim Teknis harus bisa
 * memantau siapa yang bertugas, mengganti auditor atau panelis, lalu membuat
 * ulang PDF tinjauan dan Surat Tugas — sampai order selesai.
 */
class TechnicalMonitoringDuringAuditTest extends TestCase
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
            'name' => ucfirst($roleCode).' '.Str::random(3),
            'email' => $roleCode.Str::random(4).'@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function orderPada(string $status): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();

        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Pantau',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'PTU-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $this->user('auditor')->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        ApplicationReview::create([
            'application_id' => $app->id,
            'review_type' => 'technical',
            'round' => 1,
            'status' => 'approved',
            'action_date' => today(),
        ]);

        $path = 'generated/assignment-letters/'.$app->id.'/ST_stage_1.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 surat');
        $pdf = GeneratedPdf::create([
            'application_id' => $app->id,
            'document_type' => 'assignment_letter',
            'template_code' => 'assignment_letter_lssm',
            'document_version' => 1,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', '%PDF-1.4 surat'),
            'source_snapshot' => [],
        ]);
        AssignmentLetter::create([
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
            'cycle' => 0,
            'template_code' => 'lssm',
            'number_family' => 'lssm',
            'letter_number' => '001/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'pdf_version' => 1,
            'generated_pdf_id' => $pdf->id,
        ]);

        return $app;
    }

    public static function tahapProsesProvider(): array
    {
        return [
            'sebelum audit'    => ['payment_completed'],
            'audit tahap 1'    => ['stage_1_audit'],
            'audit tahap 2'    => ['stage_2_audit'],
            'audit lapangan'   => ['qms_audit'],
            'tindakan koreksi' => ['corrective_action'],
            'review sertifikat'=> ['certificate_review'],
            'surveillance'     => ['surveillance'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tahapProsesProvider')]
    public function test_halaman_penugasan_terbuka_sepanjang_proses(string $status): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->orderPada($status);

        $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.index'))
            ->assertOk()
            ->assertSee($app->order_number);

        $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.show', $app))
            ->assertOk()
            ->assertSee('Surat Tugas');
    }

    public function test_auditor_dan_panelis_masih_bisa_diganti_saat_audit_berjalan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->orderPada('stage_1_audit');
        $tech = $this->user('technical');

        // Ganti auditor di tengah audit.
        $auditorBaru = $this->user('auditor');
        $this->actingAs($tech)->post(route('technical.audit-assignments.store', $app), [
            'auditor_id' => $auditorBaru->id,
            'assignment_role' => 'A',
            'stage_code' => 'stage_2',
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_assignments', [
            'application_id' => $app->id,
            'auditor_id' => $auditorBaru->id,
            'stage_code' => 'stage_2',
        ]);

        // Pilih ulang panelis di tengah audit.
        $panelis = $this->user('technical');
        $this->actingAs($tech)->post(route('technical.reviews.panelists', $app), [
            'panelist_ids' => [$panelis->id],
        ])->assertRedirect();

        $review = $app->reviews()->where('review_type', 'technical')->firstOrFail();
        $this->assertSame([$panelis->id], $review->fresh()->panelist_ids);
    }

    public function test_pdf_tinjauan_dan_surat_tugas_bisa_dibuat_ulang_saat_audit_berjalan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->orderPada('qms_audit');
        $tech = $this->user('technical');

        // Generate ulang PDF tinjauan.
        $this->actingAs($tech)
            ->post(route('technical.assignments.regenerate-review', $app))
            ->assertRedirect();

        $this->assertDatabaseHas('generated_pdfs', [
            'application_id' => $app->id,
            'document_type' => 'application_review',
            'document_version' => 1,
        ]);

        // Terbitkan ulang Surat Tugas tahap 1 — versinya naik, surat lama tetap tersimpan.
        $surat = AssignmentLetter::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($tech)->post(route('technical.assignments.generate', [$app, 'stage_1']), [
            'letter_number' => '002/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => now()->format('Y-m-d'),
            'auditors' => [[
                'include' => '1',
                'auditor_id' => $app->auditAssignments()->value('auditor_id'),
                'name' => 'Auditor Pengganti',
                'role_code' => 'LA',
                'position_label' => 'Lead Auditor',
            ]],
        ])->assertRedirect();

        $surat->refresh();
        $this->assertSame(2, $surat->pdf_version, 'Terbit ulang seharusnya menaikkan versi surat.');
        $this->assertSame('002/ST/GIS-LSSM/MT/VIII/2026', $surat->letter_number);
        $this->assertSame('Auditor Pengganti', $surat->auditor_rows[0]['name']);
    }

    /**
     * Inilah gunanya "Generate Ulang PDF Tinjauan".
     *
     * Formulir tinjauan memuat baris tim auditor dan panelis, dan isinya dibaca
     * dari penugasan yang berlaku saat PDF dibuat. Bila tim berganti setelah
     * permohonan disetujui, dokumen lama menyebut orang yang tidak jadi
     * bertugas. Generate ulang menerbitkan versi baru yang sesuai kenyataan,
     * tanpa menghapus versi lama — jadi jejaknya tetap dapat ditelusuri.
     */
    public function test_generate_ulang_memperbarui_tim_pada_pdf_tanpa_menghapus_versi_lama(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->orderPada('stage_1_audit');
        $tech = $this->user('technical');

        $auditorAwal = User::find($app->auditAssignments()->value('auditor_id'));
        $auditorAwal->update(['name' => 'Auditor Pertama']);

        // Versi 1 dibuat saat tim masih berisi auditor pertama.
        $this->actingAs($tech)
            ->post(route('technical.assignments.regenerate-review', $app))
            ->assertRedirect();

        $v1 = $app->generatedPdfs()->where('document_type', 'application_review')->latest('id')->firstOrFail();
        $isiV1 = Storage::disk('private')->get($v1->file_path);
        $this->assertStringContainsString('Auditor Pertama', $isiV1);

        // Auditor diganti di tengah audit.
        AuditAssignment::where('application_id', $app->id)->delete();
        $auditorBaru = $this->user('auditor');
        $auditorBaru->update(['name' => 'Auditor Pengganti']);
        AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditorBaru->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.assignments.regenerate-review', $app->fresh()))
            ->assertRedirect();

        $v2 = $app->generatedPdfs()->where('document_type', 'application_review')->latest('id')->firstOrFail();

        $this->assertSame(2, $v2->document_version, 'Generate ulang seharusnya menaikkan versi.');

        $isiV2 = Storage::disk('private')->get($v2->file_path);
        $this->assertStringContainsString('Auditor Pengganti', $isiV2, 'Versi baru belum memuat tim terkini.');
        $this->assertStringNotContainsString('Auditor Pertama', $isiV2, 'Versi baru masih memuat tim lama.');

        // Versi lama tetap utuh dan masih bisa diunduh sebagai riwayat.
        Storage::disk('private')->assertExists($v1->file_path);
        $this->assertStringContainsString('Auditor Pertama', Storage::disk('private')->get($v1->file_path));
    }

    public function test_order_sebelum_pembayaran_lunas_belum_masuk_pemantauan(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->orderPada('technical_review');

        $this->actingAs($this->user('technical'))
            ->get(route('technical.assignments.show', $app))
            ->assertStatus(422);
    }
}
