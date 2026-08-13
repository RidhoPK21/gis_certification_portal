<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\AssignmentLetterPdfService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tanda tangan berstempel hanya membubuhi Surat Tugas; PDF Tinjauan tetap
 * memakai tanda tangan elektronik dari profil akun.
 */
class AssignmentLetterSignatureTest extends TestCase
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

    private function application(): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', 'ISO9001')->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'payment_completed',
            'current_step' => 'payment_completed',
            'company_name' => 'PT Stempel',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'SIG-'.Str::random(5),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        AuditAssignment::create([
            'application_id' => $application->id,
            'auditor_id' => $this->user('auditor')->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        return $application;
    }

    private function pngUpload(string $name = 'ttd-stempel.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 200);
    }

    public function test_unggah_menyimpan_tanda_tangan_pada_surat_tahap_itu(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.signature', [$app, 'stage_1']), [
                'signature' => $this->pngUpload(),
            ])
            ->assertRedirect();

        $letter = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_1')->firstOrFail();

        $this->assertNotNull($letter->signature_path);
        $this->assertSame('ttd-stempel.png', $letter->signature_original_name);
        Storage::disk('private')->assertExists($letter->signature_path);

        // Tahap lain tidak ikut terisi.
        $this->assertNull(AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_2')->first());
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.signature', [$app, 'stage_1']), [
                'signature' => UploadedFile::fake()->create('surat.pdf', 12, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('assignment_letters', ['application_id' => $app->id, 'signature_original_name' => 'surat.pdf']);
    }

    public function test_pakai_ulang_menyalin_berkas_bukan_membagi_path(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');

        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_1']), [
            'signature' => $this->pngUpload(),
        ])->assertRedirect();

        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_2']), [
            'reuse_from_previous' => '1',
        ])->assertRedirect();

        $one = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_1')->firstOrFail();
        $two = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_2')->firstOrFail();

        $this->assertNotNull($two->signature_path);
        $this->assertNotSame($one->signature_path, $two->signature_path, 'Kedua tahap berbagi berkas yang sama.');
        Storage::disk('private')->assertExists($one->signature_path);
        Storage::disk('private')->assertExists($two->signature_path);
    }

    public function test_mengganti_berkas_di_satu_tahap_tidak_mengubah_tahap_lain(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');

        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_1']), [
            'signature' => $this->pngUpload('ttd-awal.png'),
        ])->assertRedirect();
        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_2']), [
            'reuse_from_previous' => '1',
        ])->assertRedirect();

        $stageTwoPath = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_2')->value('signature_path');

        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_1']), [
            'signature' => $this->pngUpload('ttd-baru.png'),
        ])->assertRedirect();

        $letterOne = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_1')->firstOrFail();
        $letterTwo = AssignmentLetter::where('application_id', $app->id)->where('stage_code', 'stage_2')->firstOrFail();

        $this->assertSame('ttd-baru.png', $letterOne->signature_original_name);
        $this->assertSame($stageTwoPath, $letterTwo->signature_path);
        Storage::disk('private')->assertExists($letterTwo->signature_path);
    }

    public function test_pakai_ulang_tidak_menjangkau_order_lain(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');

        $first = $this->application();
        $this->actingAs($tech)->post(route('technical.assignments.signature', [$first, 'stage_1']), [
            'signature' => $this->pngUpload(),
        ])->assertRedirect();

        $second = $this->application();

        $this->actingAs($tech)
            ->post(route('technical.assignments.signature', [$second, 'stage_1']), ['reuse_from_previous' => '1'])
            ->assertStatus(422);
    }

    public function test_pdf_memakai_tanda_tangan_berstempel_surat(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');

        $this->actingAs($tech)->post(route('technical.assignments.signature', [$app, 'stage_1']), [
            'signature' => $this->pngUpload(),
        ])->assertRedirect();

        $letter = AssignmentLetter::where('application_id', $app->id)->firstOrFail();
        $letter->update([
            'auditor_rows' => [['auditor_id' => 1, 'name' => 'Vera Marini', 'role_code' => 'LA', 'position_label' => 'Lead Auditor', 'sort' => 1]],
            'field_overrides' => ['company_name' => 'PT Stempel'],
            'signer_name' => 'Prima Sulistya',
        ]);

        $snapshot = app(AssignmentLetterPdfService::class)->snapshot($letter->refresh());
        $this->assertSame($letter->signature_path, $snapshot['signature_path']);

        $record = app(AssignmentLetterPdfService::class)->generate($letter->refresh(), $tech->id);
        $this->assertGreaterThan(1500, strlen(Storage::disk('private')->get($record->file_path)));
    }

    public function test_pdf_jatuh_ke_tanda_tangan_profil_bila_belum_ada_stempel(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();
        $tech = $this->user('technical');
        $tech->forceFill(['signature_path' => 'signatures/profil.png'])->save();

        $letter = AssignmentLetter::create([
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
            'cycle' => 0,
            'template_code' => 'lssm',
            'number_family' => 'lssm',
            'letter_number' => '001/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'signed_by' => $tech->id,
        ]);

        $snapshot = app(AssignmentLetterPdfService::class)->snapshot($letter);

        $this->assertSame('signatures/profil.png', $snapshot['signature_path']);
    }

    public function test_pratinjau_tanda_tangan_hanya_untuk_internal_penyiap_surat(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $app = $this->application();

        $this->actingAs($this->user('technical'))
            ->post(route('technical.assignments.signature', [$app, 'stage_1']), ['signature' => $this->pngUpload()])
            ->assertRedirect();

        $letter = AssignmentLetter::where('application_id', $app->id)->firstOrFail();

        foreach (['technical', 'admin_application', 'superadmin'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('secure-files.assignment-letter-signature', $letter))
                ->assertOk();
        }

        /*
         * Tim Sustain ikut ditolak: ordernya ISO9001, di luar skema yang
         * mereka tangani. Peran yang benar saja tidak cukup — berkas surat
         * mengikuti pembagian skema, sama seperti halaman ordernya.
         */
        foreach (['finance', 'auditor', 'client', 'admin_sustain', 'technical_sustain'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('secure-files.assignment-letter-signature', $letter))
                ->assertForbidden();
        }
    }
}
