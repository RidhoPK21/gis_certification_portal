<?php

namespace Tests\Feature;

use App\Models\ApplicationStatusHistory;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GisFormRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationSubmissionService;
use App\Services\DynamicFormService;
use App\Services\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

/**
 * Peta perpindahan status permohonan, diuji satu per satu.
 *
 * Test alur per modul sudah ada sendiri (FullCertificationFlowTest,
 * ReviewAdminTest, TechnicalDecisionTest, AuditTest, FinanceTest,
 * SurveillanceTest). Yang diuji di sini adalah petanya sebagai satu kesatuan:
 * setiap jalur yang dinyatakan sah benar-benar bisa dijalankan, setiap jalur
 * di luar peta benar-benar ditolak, dan dua status akhir memang buntu.
 *
 * Tabel harapan ditulis lugas di berkas ini, bukan dibaca dari
 * WorkflowService::ALLOWED, supaya perubahan pada peta tidak ikut mengubah
 * harapannya secara diam-diam. Kesamaan keduanya diperiksa terpisah pada
 * test_peta_transisi_sama_dengan_yang_didokumentasikan().
 */
class PetaAlurStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jalur sah, sesuai Gambar 4.7 pada dokumen SW.
     *
     * @return array<string, list<string>>
     */
    private function jalurSah(): array
    {
        return [
            'draft' => ['submitted'],
            'submitted' => ['admin_review'],
            'admin_review' => ['revision_requested', 'technical_review'],
            'technical_review' => ['admin_review', 'revision_requested', 'rejected', 'application_approved'],
            'revision_requested' => ['client_revision'],
            'client_revision' => ['admin_review'],
            'application_approved' => ['invoice_process'],
            'invoice_process' => ['payment_partial', 'payment_completed'],
            'payment_partial' => ['payment_partial', 'payment_completed'],
            'payment_completed' => ['stage_1_audit', 'stage_2_audit', 'qms_audit'],
            'stage_1_audit' => ['stage_2_audit', 'qms_audit'],
            'stage_2_audit' => ['qms_audit'],
            'qms_audit' => ['corrective_action', 'certificate_review'],
            'corrective_action' => ['corrective_revision', 'certificate_review'],
            'corrective_revision' => ['corrective_action'],
            'certificate_review' => ['final_certificate'],
            'final_certificate' => ['completed'],
            'completed' => ['surveillance'],
        ];
    }

    /**
     * Jalur yang sering dikira boleh, beserta alasan mengapa ditutup.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function jalurTerlarang(): array
    {
        return [
            ['draft', 'admin_review', 'permohonan harus dikirim lebih dulu'],
            ['submitted', 'technical_review', 'antrean Admin tidak boleh dilewati'],
            ['admin_review', 'application_approved', 'keputusan akhir hanya milik Tim Teknis'],
            ['admin_review', 'rejected', 'Admin Permohonan tidak dapat menolak'],
            ['revision_requested', 'admin_review', 'harus melewati perbaikan klien'],
            ['client_revision', 'technical_review', 'revisi selalu kembali lewat Admin'],
            ['application_approved', 'payment_completed', 'invoice harus diterbitkan lebih dulu'],
            ['invoice_process', 'stage_1_audit', 'audit belum boleh jalan sebelum pembayaran'],
            ['payment_completed', 'certificate_review', 'seluruh tahap audit tidak boleh dilompati'],
            ['stage_2_audit', 'stage_1_audit', 'tahap audit tidak berjalan mundur'],
            ['qms_audit', 'final_certificate', 'draft sertifikat wajib ditinjau lebih dulu'],
            ['corrective_revision', 'certificate_review', 'perbaikan koreksi dinilai auditor lebih dulu'],
            ['certificate_review', 'completed', 'sertifikat final wajib terbit lebih dulu'],
            ['rejected', 'admin_review', 'penolakan adalah titik berhenti'],
            ['rejected', 'technical_review', 'penolakan adalah titik berhenti'],
            ['surveillance', 'completed', 'surveillance adalah status terakhir'],
        ];
    }

    private function permohonan(string $status): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->klien()->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Peta Alur',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'PETA-'.Str::random(6),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function klien(): User
    {
        $klien = User::firstOrCreate(
            ['email' => 'klien.peta@example.com'],
            ['name' => 'Klien Peta', 'password' => 'RahasiaKuat123', 'is_active' => true,
                'company_name' => 'PT Peta Alur', 'phone' => '0811']
        );
        $klien->roles()->syncWithoutDetaching([Role::where('code', 'client')->value('id')]);

        return $klien;
    }

    private function pengguna(string $roleCode): User
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    public function test_peta_transisi_sama_dengan_yang_didokumentasikan(): void
    {
        $konstanta = (new ReflectionClass(WorkflowService::class))->getConstant('ALLOWED');

        $this->assertSame(
            $this->jalurSah(),
            $konstanta,
            'Peta transisi pada WorkflowService berubah. Perbarui Gambar 4.7 dan tabel di test ini.'
        );
    }

    public function test_seluruh_jalur_sah_dapat_dijalankan(): void
    {
        $workflow = app(WorkflowService::class);
        $dijalankan = 0;

        foreach ($this->jalurSah() as $dari => $tujuanList) {
            foreach ($tujuanList as $tujuan) {
                $app = $this->permohonan($dari);

                $hasil = $workflow->transition($app, $tujuan, 'uji_peta', 'Uji jalur '.$dari.' ke '.$tujuan);

                $this->assertSame($tujuan, $hasil->status, "Jalur {$dari} ke {$tujuan} seharusnya sah.");
                $this->assertSame($tujuan, $hasil->current_step);
                $this->assertDatabaseHas('application_status_history', [
                    'application_id' => $app->id,
                    'from_status' => $dari,
                    'to_status' => $tujuan,
                    'action' => 'uji_peta',
                ]);
                $dijalankan++;
            }
        }

        $this->assertSame(29, $dijalankan, 'Jumlah jalur sah pada peta transisi berubah.');
    }

    public function test_jalur_di_luar_peta_ditolak(): void
    {
        $workflow = app(WorkflowService::class);

        foreach ($this->jalurTerlarang() as [$dari, $tujuan, $alasan]) {
            $app = $this->permohonan($dari);

            try {
                $workflow->transition($app, $tujuan, 'uji_peta');
                $this->fail("Jalur {$dari} ke {$tujuan} seharusnya ditolak: {$alasan}.");
            } catch (ValidationException $e) {
                $this->assertSame(
                    "Transisi status {$dari} ke {$tujuan} tidak diizinkan.",
                    $e->errors()['status'][0]
                );
            }

            $this->assertSame($dari, $app->refresh()->status, 'Status tidak boleh berubah saat transisi ditolak.');
            $this->assertDatabaseMissing('application_status_history', [
                'application_id' => $app->id,
                'to_status' => $tujuan,
            ]);
        }
    }

    public function test_status_penolakan_dan_surveillance_adalah_titik_akhir(): void
    {
        $peta = $this->jalurSah();

        $this->assertArrayNotHasKey('rejected', $peta, 'rejected seharusnya tidak punya lanjutan.');
        $this->assertArrayNotHasKey('surveillance', $peta, 'surveillance seharusnya tidak punya lanjutan.');
    }

    /**
     * Dua perpindahan otomatis dijalankan oleh controller, bukan oleh pengguna:
     * application_approved ke invoice_process, dan completed ke surveillance.
     * Keduanya diuji lewat rute sungguhan pada FullCertificationFlowTest; di
     * sini hanya dipastikan petanya memang menyediakan jalurnya.
     */
    public function test_peta_menyediakan_jalur_untuk_dua_perpindahan_otomatis(): void
    {
        $peta = $this->jalurSah();

        $this->assertSame(['invoice_process'], $peta['application_approved']);
        $this->assertSame(['surveillance'], $peta['completed']);
    }

    public function test_revisi_dari_admin_kembali_ke_antrean_admin_lewat_klien(): void
    {
        Storage::fake('private');

        $klien = $this->klien();
        $admin = $this->pengguna('admin_application');
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();
        $app = $this->permohonanSiapKirim($klien, $admin, $scheme);

        $this->actingAs($klien)->post(route('client.applications.submit', $app))->assertRedirect();
        $this->assertSame('admin_review', $app->refresh()->status);

        $this->actingAs($admin)->post(route('internal.applications.revision', $app), [
            'targets' => [
                ['type' => 'field', 'code' => 'company_name', 'label' => 'Nama perusahaan', 'note' => 'Lengkapi nama resmi.'],
            ],
        ])->assertRedirect();
        $this->assertSame('revision_requested', $app->refresh()->status);

        $this->actingAs($klien)->post(route('client.applications.submit', $app))->assertRedirect();

        $this->assertSame('admin_review', $app->refresh()->status,
            'Perbaikan klien harus kembali ke antrean Admin, bukan langsung ke Tim Teknis.');

        $jejak = ApplicationStatusHistory::where('application_id', $app->id)
            ->orderBy('id')->pluck('to_status')->all();

        $this->assertSame([
            'submitted',
            'admin_review',
            'revision_requested',
            'client_revision',
            'admin_review',
        ], $jejak, 'Urutan jalur revisi tidak sesuai peta.');
    }

    /**
     * Draft yang seluruh field wajib dan dokumen wajibnya sudah lengkap,
     * sehingga siap dikirim lewat rute klien.
     */
    private function permohonanSiapKirim(User $klien, User $admin, CertificationScheme $scheme): CertificationApplication
    {
        $submissions = app(ApplicationSubmissionService::class);
        $forms = app(DynamicFormService::class);

        $app = $submissions->createDraft($klien->id, $scheme->id, [
            'company_name' => 'PT Peta Alur',
            'applicant_name' => 'Budi',
            'contact_email' => 'budi@uji.test',
            'contact_phone' => '0811',
            'form_version' => $scheme->form_version,
        ]);

        $snapshot = $forms->schemeForApplication($app);
        $values = [];
        foreach ($snapshot->sections->flatMap->fields->where('is_required', true) as $field) {
            $values[$field->code] = match ($field->type) {
                'select', 'radio' => $field->options->first()->value ?? 'x',
                'boolean' => 'yes',
                'email' => 'kontak@uji.test',
                'number' => '10',
                'date' => '2026-01-01',
                'url' => 'https://uji.test',
                'checkbox_group' => [$field->options->first()->value ?? 'x'],
                default => 'Isian uji',
            };
        }
        $submissions->saveValues($app, $values, $klien->id);

        // Slot Formulir Wajib GIS baru terbuka setelah Admin menyetujui permintaan template.
        $this->actingAs($klien)->post(route('client.gis-form-requests.store', $app));
        $permintaan = GisFormRequest::where('application_id', $app->id)->first();
        if ($permintaan) {
            $this->actingAs($admin)->post(route('internal.gis-form-requests.approve', $permintaan));
        }

        foreach ($forms->applicableDocuments($snapshot, $values)->where('requirement', 'required') as $dokumen) {
            $this->actingAs($klien)->post(route('client.documents.store', $app), [
                'document_code' => $dokumen->code,
                'file' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
            ]);
        }

        return $app->refresh();
    }
}
