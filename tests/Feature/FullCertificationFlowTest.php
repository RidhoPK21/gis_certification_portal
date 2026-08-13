<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GeneratedPdf;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rantai penuh satu order di bawah aturan baru, dari tinjauan teknis sampai
 * surveillance.
 *
 * Test lain menguji tiap tahap secara terpisah. Yang diuji di sini adalah
 * sambungannya — terutama bahwa gerbang Surat Tugas yang baru tidak membuat
 * proses buntu di tengah jalan, dan bahwa order benar-benar kembali ke Tim
 * Teknis untuk penerbitan sertifikat setelah audit selesai.
 */
class FullCertificationFlowTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Terbitkan Surat Tugas satu tahap tanpa melewati UI penerbitan, yang
     * pengujiannya sudah menjadi tanggung jawab AssignmentLetterTest.
     */
    private function issueLetter(CertificationApplication $app, string $stage): void
    {
        $path = 'generated/assignment-letters/'.$app->id.'/ST_'.$stage.'.pdf';
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
            'stage_code' => $stage,
            'cycle' => 0,
            'template_code' => 'lssm',
            'number_family' => 'lssm',
            'letter_number' => '00'.rand(1, 9).'/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'pdf_version' => 1,
            'generated_pdf_id' => $pdf->id,
        ]);
    }

    public function test_order_berjalan_dari_tinjauan_teknis_sampai_surveillance(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);

        $client = $this->user('client');
        $admin = $this->user('admin_application');
        $technical = $this->user('technical');
        $finance = $this->user('finance');
        $auditor = $this->user('auditor');

        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();
        $app = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Rantai Penuh',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'FULL-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        // --- Admin: kajian administrasi lalu teruskan -----------------------
        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('internal.applications.forward-technical', $app))->assertRedirect();
        $this->assertSame('technical_review', $app->refresh()->status);

        // --- Teknis: tim auditor, tinjauan, lalu keputusan ------------------
        $this->actingAs($technical)->post(route('technical.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($technical)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Teknis',
        ])->assertRedirect();

        $this->actingAs($technical)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Disetujui.',
        ])->assertRedirect();

        $this->assertSame('invoice_process', $app->refresh()->status);

        // --- Finance: invoice dan pelunasan ---------------------------------
        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-'.Str::random(5),
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'tahap_1',
            'amount' => 10000000,
        ])->assertRedirect();

        $this->actingAs($finance)->post(route('finance.payment', $app), [
            'milestone' => 1,
            'amount' => 10000000,
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'verified',
            'mark_lunas' => 1,
        ])->assertRedirect();

        $this->assertSame('payment_completed', $app->refresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $technical->id,
            'type' => 'assignment_letter_pending',
        ]);

        // --- Gerbang: auditor belum boleh bekerja ---------------------------
        $this->actingAs($auditor)->post(route('audit.stage', $app), [
            'stage_code' => 'stage_1',
            'status' => 'approved',
            'audit_date' => now()->format('Y-m-d'),
            'auditor_team' => 'LA',
        ])->assertForbidden();

        // --- Teknis menerbitkan Surat Tugas tiap tahap ----------------------
        foreach (['stage_1', 'stage_2', 'qms'] as $stage) {
            $this->issueLetter($app, $stage);
        }

        // --- Auditor: tiga tahap audit --------------------------------------
        foreach (['stage_1', 'stage_2', 'qms'] as $stage) {
            $this->actingAs($auditor)->post(route('audit.stage', $app), [
                'stage_code' => $stage,
                'status' => 'approved',
                'audit_date' => now()->format('Y-m-d'),
                'auditor_team' => 'LA: Auditor',
            ])->assertRedirect();
        }

        $this->assertSame('qms_audit', $app->refresh()->status);

        // --- Auditor menutup audit: order kembali ke Tim Teknis --------------
        $this->actingAs($auditor)->post(route('audit.complete', $app), [
            'notes' => 'Audit selesai tanpa temuan terbuka.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('certificate_review', $app->refresh()->status,
            'Setelah audit selesai order harus kembali ke Tim Teknis untuk penerbitan sertifikat.');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $technical->id,
            'type' => 'certificate_review',
        ]);

        // --- Teknis: draft, sertifikat final, lalu penutupan -----------------
        $this->actingAs($technical)->post(route('technical.draft.upload', $app), [
            'draft' => UploadedFile::fake()->create('draft.pdf', 20, 'application/pdf'),
            'notes' => 'Draft sertifikat.',
        ])->assertRedirect();

        $this->actingAs($technical)->post(route('technical.final.upload', $app), [
            'certificate' => UploadedFile::fake()->create('final.pdf', 20, 'application/pdf'),
            'certificate_number' => 'GIS-'.Str::random(5),
            'issued_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addYears(3)->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('final_certificate', $app->refresh()->status);

        $this->actingAs($technical)->post(route('technical.complete', $app), [
            'notes' => 'Sertifikasi selesai.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        // Rencana surveillance dibuat otomatis saat sertifikat final diunggah,
        // sehingga penutupan langsung mengaktifkan tahap surveillance.
        $this->assertSame('surveillance', $app->refresh()->status,
            'Order harus berakhir pada tahap surveillance yang dikelola Tim Teknis.');
        $this->assertTrue($app->surveillanceSchedules()->exists());

        /*
         * Rekap perjalanan status.
         *
         * Dibaca langsung dari tabel, bukan lewat relasi statusHistory: relasi
         * itu diurutkan berdasarkan action_date, dan seluruh transisi di test
         * ini terjadi pada tanggal yang sama sehingga urutannya tidak pasti.
         */
        $jejak = \App\Models\ApplicationStatusHistory::where('application_id', $app->id)
            ->orderBy('id')
            ->pluck('to_status')
            ->all();

        $this->assertSame([
            'technical_review',
            'application_approved',
            'invoice_process',
            'payment_partial',
            'payment_completed',
            'stage_1_audit',
            'stage_2_audit',
            'qms_audit',
            'certificate_review',
            'final_certificate',
            'completed',
            'surveillance',
        ], $jejak, 'Urutan tahap tidak sesuai alur yang dirancang.');
    }
}
