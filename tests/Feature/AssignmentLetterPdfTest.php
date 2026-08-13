<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\AssignmentLetterPdfService;
use App\Services\AssignmentLetterService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Penggambar PDF Surat Tugas untuk ketiga keluarga skema.
 *
 * Menguji service secara langsung, tanpa lewat HTTP, supaya kegagalan tata
 * letak terpisah jelas dari kegagalan controller atau otorisasi.
 */
class AssignmentLetterPdfTest extends TestCase
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

    private function application(string $schemeCode): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user('client')->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'payment_completed',
            'current_step' => 'payment_completed',
            'company_name' => 'PT Surat Tugas '.$schemeCode,
            'contact_email' => 'kontak@uji.test',
            'contact_phone' => '021-555-0000',
            'order_number' => 'ST-'.$schemeCode.'-'.Str::random(4),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    private function letterFor(string $schemeCode, string $stage = 'stage_1'): AssignmentLetter
    {
        $application = $this->application($schemeCode);
        $service = app(AssignmentLetterService::class);
        $technical = $this->user('technical');

        foreach ([['LA', 'Vera Marini'], ['A', 'Billy Yosua']] as [$role, $name]) {
            $auditor = User::create([
                'name' => $name,
                'email' => Str::slug($name).Str::random(3).'@example.com',
                'password' => 'RahasiaKuat123',
                'is_active' => true,
            ]);
            $auditor->roles()->attach(Role::where('code', 'auditor')->value('id'));

            AuditAssignment::create([
                'application_id' => $application->id,
                'auditor_id' => $auditor->id,
                'assignment_role' => $role,
                'stage_code' => 'all',
                'assigned_date' => today(),
                'status' => 'assigned',
            ]);
        }

        $application->load('auditAssignments.auditor');
        $family = $service->family($application);
        $suggested = $service->suggestNumber($family, now());

        return AssignmentLetter::create([
            'application_id' => $application->id,
            'stage_code' => $stage,
            'cycle' => 0,
            'template_code' => $application->scheme->review_template,
            'number_family' => $family,
            'sequence_number' => $suggested['sequence'],
            'letter_number' => $suggested['number'],
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'assignment_start_date' => today()->addDays(7),
            'assignment_end_date' => today()->addDays(8),
            'auditor_rows' => $service->auditorRows($application, $stage),
            'field_overrides' => array_merge($service->defaults($application, $stage), [
                'laboratory' => 'PT Global Inspeksi Forensik Teknik',
                'laboratory_address' => 'Komplek 91 Distrik BSD Blok C5, Tangerang',
                'scope' => 'Integrasi Budi Daya dan Pengolahan Kelapa Sawit',
                'location' => 'Desa Sukaramai, Kabupaten Ketapang',
            ]),
            'signer_name' => 'Prima Sulistya',
            'signed_by' => $technical->id,
        ]);
    }

    public static function familyProvider(): array
    {
        return [
            'LSSM (ISO 9001)' => ['ISO9001', 'lssm', 'SURAT TUGAS'],
            'LSML (ISO 14001)' => ['ISO14001', 'lssm', 'SURAT TUGAS'],
            'LSPro (SNI Lokal)' => ['SNI_LOKAL', 'lspro', 'ASSIGNMENT LETTER'],
            'ISPO' => ['ISPO', 'ispo', 'SURAT TUGAS'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('familyProvider')]
    public function test_pdf_surat_tugas_terbentuk_untuk_tiap_keluarga(string $schemeCode, string $family, string $title): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor($schemeCode);
        $this->assertSame($family, $letter->number_family, $schemeCode.': keluarga surat salah.');

        $record = app(AssignmentLetterPdfService::class)->generate($letter, $letter->signed_by);

        $this->assertSame('assignment_letter', $record->document_type);
        $this->assertSame('assignment_letter_'.$family, $record->template_code);
        $this->assertSame(1, $record->document_version);

        $letter->refresh();
        $this->assertSame($record->id, $letter->generated_pdf_id);
        $this->assertSame(1, $letter->pdf_version);

        $raw = Storage::disk('private')->get($record->file_path);
        $this->assertGreaterThan(1500, strlen($raw), $schemeCode.': PDF Surat Tugas nyaris kosong.');

        // Isi kunci harus tercetak: judul, nomor, nama auditor, dan posisinya.
        foreach ([$title, $letter->letter_number, 'Vera Marini', 'Lead Auditor', 'Billy Yosua'] as $marker) {
            $this->assertStringContainsString($marker, $raw, $schemeCode.': teks "'.$marker.'" tidak ada pada PDF.');
        }
    }

    public function test_ispo_mencetak_tahap_audit_pada_kalimat_tujuan(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $stageOne = $this->letterFor('ISPO', 'stage_1');
        $rawOne = Storage::disk('private')->get(
            app(AssignmentLetterPdfService::class)->generate($stageOne)->file_path
        );
        $this->assertStringContainsString('Tahap Pertama', $rawOne);

        $stageTwo = $this->letterFor('ISPO', 'stage_2');
        $rawTwo = Storage::disk('private')->get(
            app(AssignmentLetterPdfService::class)->generate($stageTwo)->file_path
        );
        $this->assertStringContainsString('Tahap Kedua', $rawTwo);
    }

    public function test_lspro_mencetak_isian_laboratorium_yang_diketik_manual(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor('SNI_LOKAL');
        $raw = Storage::disk('private')->get(
            app(AssignmentLetterPdfService::class)->generate($letter)->file_path
        );

        $this->assertStringContainsString('Laboratory', $raw);
        $this->assertStringContainsString('PT Global Inspeksi Forensik Teknik', $raw);
        $this->assertStringContainsString('Commodity', $raw);
    }

    public function test_generate_ulang_menaikkan_versi_dan_memindah_pdf_berlaku(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor('ISO9001');
        $service = app(AssignmentLetterPdfService::class);

        $first = $service->generate($letter);
        $second = $service->generate($letter->refresh());

        $this->assertSame(1, $first->document_version);
        $this->assertSame(2, $second->document_version);
        $this->assertSame($second->id, $letter->refresh()->generated_pdf_id);
        $this->assertSame(2, $letter->pdf_version);

        // Versi lama tetap tersimpan sebagai riwayat.
        Storage::disk('private')->assertExists($first->file_path);
    }

    public function test_nomor_saran_mengikuti_pola_tiap_keluarga(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $service = app(AssignmentLetterService::class);
        $date = \Illuminate\Support\Carbon::create(2026, 2, 9);

        $this->assertSame('001/LSPr-ST/GIS/MT/II/2026', $service->suggestNumber('lspro', $date)['number']);
        $this->assertSame('001/ST/GIS-LSSM/MT/II/2026', $service->suggestNumber('lssm', $date)['number']);
        $this->assertSame('001/ISPO-ST/GIS/MT/II/2026', $service->suggestNumber('ispo', $date)['number']);
    }

    /**
     * Blok penutup dan tanda tangan rata kiri.
     *
     * Ketiga template .docx asli memakai perataan kiri dengan indentasi kecil
     * (±180 twips) untuk blok ini. Versi pertama fitur ini keliru menaruhnya di
     * sisi kanan, jadi posisinya dikunci di sini.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('familyProvider')]
    public function test_blok_tanda_tangan_rata_kiri(string $schemeCode, string $family, string $title): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor($schemeCode);
        $record = app(AssignmentLetterPdfService::class)->generate($letter);
        $raw = Storage::disk('private')->get($record->file_path);

        /*
         * SimplePdf menulis posisi teks sebagai "1 0 0 1 {x} {y} Tm (isi) Tj".
         * Nama penanda tangan dicetak dalam tanda kurung, dan kurung itu
         * di-escape menjadi \( di dalam stream — karena itu pola di bawah
         * membolehkan karakter apa pun sebelum namanya.
         */
        $pola = '/1 0 0 1 (\d+(?:\.\d+)?) [\d.]+ Tm \([^)]*Prima Sulistya/';

        $this->assertMatchesRegularExpression(
            $pola,
            $raw,
            $schemeCode.': baris nama penanda tangan tidak ditemukan pada PDF.'
        );

        preg_match($pola, $raw, $m);
        $x = (float) $m[1];

        // Margin halaman 42; sisi kanan mulai jauh di atas 300.
        $this->assertLessThan(
            120,
            $x,
            $schemeCode.': blok tanda tangan berada di sisi kanan (x='.$x.'), seharusnya rata kiri seperti template.'
        );
    }

    /**
     * Kop surat berwarna dan blok penutup seragam di ketiga template.
     *
     * Warna diambil dari berkas .docx asli — nama lembaga biru tua 1F477B,
     * tagline merah FF0000 — dan blok penutupnya sama untuk semua keluarga
     * skema, bukan hanya LSPro.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('familyProvider')]
    public function test_kop_berwarna_dan_blok_penutup_seragam(string $schemeCode, string $family, string $title): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor($schemeCode);
        $record = app(AssignmentLetterPdfService::class)->generate($letter);
        $raw = Storage::disk('private')->get($record->file_path);

        // 1F477B dan FF0000 setelah dinormalkan SimplePdf menjadi operator warna.
        $this->assertStringContainsString('0.1216 0.2784 0.4824 rg', $raw, $schemeCode.': nama lembaga tidak berwarna biru.');
        $this->assertStringContainsString('1 0 0 rg', $raw, $schemeCode.': tagline tidak berwarna merah.');
        $this->assertStringContainsString('0.1216 0.2784 0.4824 RG', $raw, $schemeCode.': garis kop tidak berwarna.');

        $this->assertStringContainsString('Products Certification Body', $raw, $schemeCode.': blok penutup tidak lengkap.');
        $this->assertStringContainsString('Administration Manager', $raw, $schemeCode.': jabatan penanda tangan tidak tercetak.');
    }

    public function test_pdf_tetap_terbentuk_tanpa_tanda_tangan(): void
    {
        Storage::fake('private');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);

        $letter = $this->letterFor('ISO9001');
        $letter->update(['signature_path' => null, 'signed_by' => null]);

        $record = app(AssignmentLetterPdfService::class)->generate($letter->refresh());

        $this->assertGreaterThan(1500, strlen(Storage::disk('private')->get($record->file_path)));
    }
}
