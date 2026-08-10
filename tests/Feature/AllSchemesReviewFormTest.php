<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
use App\Models\ApplicationValue;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\GeneratedPdf;
use App\Models\Role;
use App\Models\User;
use App\Services\IspoReviewService;
use App\Services\ReviewService;
use Database\Seeders\GisFormTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menjalankan alur tinjauan permohonan lengkap — Admin lalu Tim Teknis — untuk
 * SETIAP skema di katalog, dan mengeluarkan formulir hasilnya ke
 * storage/app/test-output/review-forms/ supaya dapat diperiksa langsung:
 *
 *   {KODE}-form-admin.html    formulir tinjauan yang dilihat Admin Permohonan
 *   {KODE}-form-teknis.html   formulir tinjauan yang dilihat Tim Teknis
 *   {KODE}-tinjauan.pdf       formulir cetak hasil kedua bagian
 *   RINGKASAN.md              daftar baris tiap bagian untuk semua skema
 *
 * Nilainya bukan sekadar dump: satu skema yang kehilangan baris tinjauan,
 * salah kop formulir, atau macet di tengah alur akan menggagalkan test ini,
 * sedangkan sebelumnya hanya sebagian skema yang punya test sendiri.
 */
class AllSchemesReviewFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kop formulir yang harus tercetak per template. Untuk lssm/lsml kodenya
     * berbeda per skema sehingga dibaca dari config review.form_meta.
     */
    /**
     * Ukuran gambar tanda tangan tiap peninjau, dibuat berbeda supaya keduanya
     * dapat dikenali terpisah pada berkas PDF ("/Width w /Height h").
     */
    private const SIGNATURE_SIZES = ['admin' => [120, 40], 'technical' => [132, 44]];

    private const TEMPLATE_MARKERS = [
        'sni' => ['Fr.7201/GIS-4', 'Checklist Dokumen Permohonan', 'Hasil Kajian Peninjau'],
        'ispo' => ['FrO.7204/GIS-3', '4. Kesesuaian Data Formulir Aplikasi', '8. HASIL TINJAUAN PERMOHONAN'],
    ];

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = storage_path('app/test-output/review-forms');
    }

    public function test_setiap_skema_menghasilkan_formulir_tinjauan_admin_dan_teknis(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->seed(GisFormTemplateSeeder::class);

        // PDF ditulis ke disk palsu; yang disalin ke folder keluaran hanya isinya.
        Storage::fake('private');
        $this->resetOutputDir();

        // Tanpa tanda kurung pada nama: PDF mengescape "(" sehingga nama seperti
        // "Helvina (Admin)" tidak akan pernah cocok saat diperiksa pada berkasnya.
        $admin = $this->user('admin_application', 'Helvina Admin Permohonan');
        $technical = $this->user('technical', 'Rangga Tim Teknis');
        $panelist = $this->user('technical', 'Dewi Panelis');
        $auditor = $this->user('auditor', 'Bayu Lead Auditor');
        $client = $this->user('client', 'PT Contoh Sejahtera');

        /*
         * Tanda tangan tidak diunggah per formulir: yang dipakai adalah tanda
         * tangan elektronik pada profil peninjau, ditempel otomatis saat PDF
         * dibuat. Keduanya dipasang di sini agar formulir keluaran memuatnya.
         */
        $this->giveSignature($admin, ...self::SIGNATURE_SIZES['admin']);
        $this->giveSignature($technical, ...self::SIGNATURE_SIZES['technical']);

        $schemes = CertificationScheme::orderBy('sort_order')->orderBy('code')->get();
        $this->assertNotEmpty($schemes, 'Katalog skema kosong; seeder tidak berjalan.');

        $summary = [];

        foreach ($schemes as $scheme) {
            $summary[] = $this->jalankanTinjauan($scheme, $admin, $technical, $panelist, $auditor, $client);
        }

        $this->writeSummary($summary);
        $this->printSummary($summary);
    }

    /**
     * Satu skema, satu permohonan, dari kajian administrasi sampai PDF final.
     *
     * @return array<string, mixed>
     */
    private function jalankanTinjauan(
        CertificationScheme $scheme,
        User $admin,
        User $technical,
        User $panelist,
        User $auditor,
        User $client
    ): array {
        $context = $scheme->code.' ('.$scheme->review_template.')';
        $application = $this->application($scheme, $client);
        $this->storeValues($application, $scheme);

        $isIspo = $scheme->review_template === 'ispo';
        $isSni = $scheme->review_template === 'sni';

        $adminRows = $isIspo
            ? app(IspoReviewService::class)->rows($application, 'administration')
            : app(ReviewService::class)->formRows($application, 'administration')->all();

        $this->assertNotEmpty($adminRows, $context.': tabel tinjauan administrasi kosong.');

        // --- Formulir Admin Permohonan ------------------------------------
        $this->actingAs($admin)
            ->get(route('internal.applications.show', $application))
            ->assertOk()
            ->assertSee($application->order_number, false)
            ->tap(fn ($response) => $this->dump($scheme->code.'-form-admin.html', $response->getContent()));

        $this->actingAs($admin)
            ->post(route('internal.applications.review', $application), array_filter([
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
                'site_count' => 2,
                'notes' => 'Dokumen administrasi diterima lengkap dan dapat diproses lebih lanjut.',
                'items' => $this->itemPayload($adminRows, $isIspo),
                'ispo' => $isIspo ? [
                    'documents_received_at' => now()->format('Y-m-d'),
                    'initial_completeness' => 'lengkap',
                    'administrative_notes' => 'Berkas awal lengkap sesuai FrO.7201.',
                ] : null,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Tim auditor pada formulir diambil dari penugasan, bukan diketik ulang.
        AuditAssignment::create([
            'application_id' => $application->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => today(),
            'status' => 'assigned',
        ]);

        $this->actingAs($admin)
            ->post(route('internal.applications.forward-technical', $application))
            ->assertRedirect();

        $this->assertSame('technical_review', $application->refresh()->status, $context.': permohonan tidak sampai ke Tim Teknis.');

        // --- Formulir Tim Teknis ------------------------------------------
        $technicalRows = $isIspo
            ? app(IspoReviewService::class)->rows($application, 'technical')
            : app(ReviewService::class)->formRows($application, 'technical')->all();

        /*
         * LSPro tidak menilai ulang baris per baris (Tim Teknis memverifikasi
         * kajian Admin secara menyeluruh), jadi tabel teknisnya boleh mengikuti
         * dokumen bergrup 'both'. Skema lain wajib punya barisnya sendiri.
         */
        if (! $isSni) {
            $this->assertNotEmpty($technicalRows, $context.': tabel tinjauan teknis kosong.');
        }

        $this->actingAs($technical)
            ->get(route('technical.reviews.show', $application))
            ->assertOk()
            ->tap(fn ($response) => $this->dump($scheme->code.'-form-teknis.html', $response->getContent()));

        $this->actingAs($technical)
            ->post(route('technical.reviews.save', $application), array_filter([
                'action_date' => now()->format('Y-m-d'),
                'notes' => 'Kajian teknis selesai; lembaga mampu melaksanakan audit sesuai ruang lingkup.',
                'scope_conformity' => 'sesuai',
                'audit_capability_choice' => 'dapat',
                'panelist_ids' => [$panelist->id],
                'aspects' => [
                    'audit_mandays' => '6',
                    'required_auditor_competence' => 'Auditor '.$scheme->code.' dengan pengalaman sektor terkait.',
                ],
                'auditor_competence_codes' => $scheme->review_template === 'lsml' ? ['6.1', '6.6'] : null,
                'sni_verification' => $isSni ? 'accept' : null,
                'items' => $isSni ? null : $this->itemPayload($technicalRows, $isIspo),
                'ispo' => $isIspo ? [
                    'decision' => 'approved',
                    'mandays' => ['hulu_budidaya' => ['stage_1' => '2', 'stage_2' => '4', 'note' => 'Luas kebun < 1.000 ha.']],
                    'follow_up' => 'Lanjut ke penjadwalan audit tahap 1.',
                ] : null,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($technical)
            ->post(route('technical.reviews.complete', $application))
            ->assertRedirect();

        // --- Keputusan Admin: PDF tinjauan dibuat di sini -------------------
        $this->actingAs($admin)
            ->post(route('internal.applications.approve', $application->refresh()), [
                'action_date' => now()->format('Y-m-d'),
                'notes' => 'Permohonan disetujui setelah tinjauan administrasi dan teknis.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertReviewsSaved($application, $admin, $technical, $context);

        $pdf = GeneratedPdf::where('application_id', $application->id)
            ->where('document_type', 'application_review')
            ->latest('document_version')
            ->first();

        $this->assertNotNull($pdf, $context.': PDF tinjauan permohonan tidak dibuat saat permohonan disetujui.');
        $this->assertSame($scheme->review_template, $pdf->template_code, $context.': PDF memakai template yang salah.');

        $raw = Storage::disk('private')->get($pdf->file_path);
        $this->assertGreaterThan(2000, strlen($raw), $context.': PDF tinjauan nyaris kosong.');

        foreach ($this->expectedMarkers($scheme) as $marker) {
            $this->assertStringContainsString($marker, $raw, $context.': teks "'.$marker.'" tidak ada pada PDF tinjauan.');
        }

        // Kedua nama penanda tangan harus tercetak: bagian admin dan bagian teknis.
        foreach ([$admin->name, $technical->name] as $name) {
            $this->assertStringContainsString($name, $raw, $context.': nama penanda tangan "'.$name.'" tidak tercetak.');
        }

        /*
         * Gambar tanda tangan kedua peninjau harus benar-benar tertanam pada
         * PDF — bukan sekadar kotak bergaris dengan nama ketik.
         */
        foreach (self::SIGNATURE_SIZES as $role => [$width, $height]) {
            $this->assertStringContainsString(
                '/Width '.$width.' /Height '.$height,
                $raw,
                $context.': tanda tangan '.$role.' tidak tertempel pada formulir.'
            );
        }

        $file = $scheme->code.'-tinjauan.pdf';
        $this->dump($file, $raw);

        return [
            'scheme' => $scheme->code,
            'name' => $scheme->name,
            'template' => $scheme->review_template,
            'form_code' => $this->formCode($scheme),
            'admin_rows' => $this->rowLabels($adminRows, $isIspo),
            'technical_rows' => $this->rowLabels($technicalRows, $isIspo),
            'pdf' => $file,
        ];
    }

    /**
     * Kedua bagian tinjauan harus tersimpan sebagai catatan terpisah dengan
     * penanda tangan masing-masing — bukan satu bagian yang menimpa lainnya.
     */
    private function assertReviewsSaved(
        CertificationApplication $application,
        User $admin,
        User $technical,
        string $context
    ): void {
        foreach (['administration' => $admin, 'technical' => $technical] as $type => $reviewer) {
            $review = ApplicationReview::where('application_id', $application->id)
                ->where('review_type', $type)
                ->latest('id')
                ->first();

            $this->assertNotNull($review, $context.': tinjauan '.$type.' tidak tersimpan.');
            $this->assertSame($reviewer->name, $review->signed_name, $context.': penanda tangan bagian '.$type.' bukan peninjaunya.');
            // Jejak tanda tangan siapa yang tercetak pada versi PDF ini.
            $this->assertSame($reviewer->signature_path, $review->signature_path, $context.': tanda tangan bagian '.$type.' tidak tercatat.');
            $this->assertNotNull($review->completed_at, $context.': tinjauan '.$type.' belum ditutup.');
            $this->assertSame('approved', $review->status, $context.': tinjauan '.$type.' tidak berstatus disetujui.');
        }
    }

    /**
     * Teks yang wajib muncul pada PDF sebuah skema: kop formulir plus penanda
     * kedua bagian tinjauan.
     *
     * @return array<int, string>
     */
    private function expectedMarkers(CertificationScheme $scheme): array
    {
        if (isset(self::TEMPLATE_MARKERS[$scheme->review_template])) {
            return self::TEMPLATE_MARKERS[$scheme->review_template];
        }

        return [$this->formCode($scheme), 'I. Administrasi', 'II. Teknis'];
    }

    private function formCode(CertificationScheme $scheme): string
    {
        return match ($scheme->review_template) {
            'sni' => 'Fr.7201/GIS-4',
            'ispo' => config('review.ispo.form.code'),
            default => config('review.form_meta.'.$scheme->code.'.code', config('review.form_meta_default.code')),
        };
    }

    /**
     * Isian baris tabel tinjauan. Baris berketerangan bebas (mis. "Level MS")
     * diisi teks, baris dokumen memakai pilihan Keterangan*) formulir.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function itemPayload(array $rows, bool $isIspo): array
    {
        return array_map(function ($row) use ($isIspo) {
            if ($isIspo) {
                return [
                    'type' => $row['type'],
                    'code' => $row['code'],
                    'label' => $row['label'],
                    'status' => 'sufficient',
                    'notes' => 'Diperiksa dan sesuai.',
                ];
            }

            return array_filter([
                'type' => 'document',
                'code' => $row->code,
                'label' => $row->name,
                'status' => 'sufficient',
                'remark_option' => $row->free_remark ? null : 'sesuai',
                'notes' => $row->free_remark ? 'Sesuai hasil verifikasi berkas.' : null,
            ], fn ($value) => $value !== null);
        }, $rows);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, string>
     */
    private function rowLabels(array $rows, bool $isIspo): array
    {
        return array_map(
            fn ($row) => $isIspo ? $row['label'] : $row->name,
            $rows
        );
    }

    private function application(CertificationScheme $scheme, User $client): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Contoh Sejahtera',
            'contact_email' => 'kontak@contoh.test',
            'order_number' => 'UJI/'.$scheme->code.'/001',
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Isian identitas pemohon supaya formulir yang dikeluarkan tidak penuh
     * tanda "-" dan blok berpilihan (jenis sertifikasi, area audit, dst.)
     * benar-benar tercetak dengan salah satu opsi tercoret.
     */
    private function storeValues(CertificationApplication $application, CertificationScheme $scheme): void
    {
        $values = [
            'company_address' => 'Jl. Contoh Raya No. 1, Jakarta Selatan',
            'industry_scope' => 'Industri pengolahan',
            'business_sector' => 'Industri pengolahan',
            'iaf_code' => '17',
            'nace_code' => 'C 25.11',
            'certification_scope' => 'Produksi dan distribusi komponen logam',
            'certification_type' => 'initial',
            'site_type' => 'multi',
            'audit_type' => 'single',
            'k3_risk_level' => 'Menengah',
            'audit_language' => 'Bahasa Indonesia',
        ];

        if ($scheme->review_template === 'sni') {
            $values += [
                'product_category' => 'Baja tulangan beton',
                'product_name' => 'BjTS 420B',
                'sni_number' => 'SNI 2052:2017',
                'brand' => 'CONTOH STEEL',
                'shipper_name' => 'Contoh Trading Ltd.',
                'shipper_address' => 'Shanghai, Tiongkok',
                'producer_name' => 'PT Contoh Sejahtera',
                'sampling_address' => 'Jl. Pabrik No. 9, Bekasi',
            ];
        }

        foreach ($values as $code => $value) {
            ApplicationValue::create([
                'application_id' => $application->id,
                'field_code' => $code,
                'value_text' => $value,
                'field_label_snapshot' => $code,
                'field_type_snapshot' => 'text',
            ]);
        }

        if ($scheme->review_template === 'ispo') {
            // Ruang lingkup pemohon menentukan kelompok checklist FrO.7204.
            ApplicationValue::create([
                'application_id' => $application->id,
                'field_code' => 'applicant_type',
                'value_json' => ['perusahaan_perkebunan'],
                'field_label_snapshot' => 'Jenis Pemohon',
                'field_type_snapshot' => 'checkbox',
            ]);
        }

        $application->unsetRelation('values');
    }

    private function user(string $roleCode, string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $roleCode.Str::random(6).'@example.test',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    /**
     * Tanda tangan elektronik pada profil peninjau, sebagaimana hasil unggahan
     * di halaman Profil (selalu disimpan sebagai JPEG di disk privat).
     */
    private function giveSignature(User $user, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 4, $height - 8, $width - 4, 8, imagecolorallocate($image, 20, 20, 90));

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();

        $path = 'signatures/'.$user->id.'_esign.jpg';
        Storage::disk('private')->put($path, $jpeg);
        $user->forceFill(['signature_path' => $path])->save();
    }

    private function resetOutputDir(): void
    {
        File::deleteDirectory($this->outputDir);
        File::ensureDirectoryExists($this->outputDir);
    }

    private function dump(string $filename, string $contents): void
    {
        File::put($this->outputDir.DIRECTORY_SEPARATOR.$filename, $contents);
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     */
    private function writeSummary(array $summary): void
    {
        $lines = [
            '# Formulir Tinjauan Permohonan — Semua Skema',
            '',
            'Dihasilkan oleh `tests/Feature/AllSchemesReviewFormTest.php` pada '.now()->format('d/m/Y H:i').'.',
            '',
            '| Skema | Template | Kode Formulir | Baris Admin | Baris Teknis | PDF |',
            '|---|---|---|---|---|---|',
        ];

        foreach ($summary as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %d | %d | %s |',
                $row['scheme'],
                $row['template'],
                $row['form_code'],
                count($row['admin_rows']),
                count($row['technical_rows']),
                $row['pdf']
            );
        }

        foreach ($summary as $row) {
            $lines[] = '';
            $lines[] = '## '.$row['scheme'].' — '.$row['name'];
            $lines[] = '';
            $lines[] = 'Kode formulir: **'.$row['form_code'].'** (template `'.$row['template'].'`)';

            foreach ([
                'Bagian Administrasi (Admin Permohonan)' => $row['admin_rows'],
                'Bagian Teknis (Tim Teknis)' => $row['technical_rows'],
            ] as $title => $rows) {
                $lines[] = '';
                $lines[] = '### '.$title.' — '.count($rows).' baris';
                $lines[] = '';

                foreach ($rows as $index => $label) {
                    $lines[] = ($index + 1).'. '.$label;
                }

                if (! $rows) {
                    $lines[] = '_Tidak ada baris dokumen; peninjau memverifikasi kajian Admin secara menyeluruh._';
                }
            }
        }

        $this->dump('RINGKASAN.md', implode(PHP_EOL, $lines).PHP_EOL);
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     */
    private function printSummary(array $summary): void
    {
        $out = PHP_EOL.'Formulir tinjauan permohonan tersimpan di: '.$this->outputDir.PHP_EOL;
        $out .= str_pad('SKEMA', 12).str_pad('TEMPLATE', 10).str_pad('FORMULIR', 18).str_pad('ADMIN', 8).'TEKNIS'.PHP_EOL;

        foreach ($summary as $row) {
            $out .= str_pad($row['scheme'], 12)
                .str_pad($row['template'], 10)
                .str_pad($row['form_code'], 18)
                .str_pad((string) count($row['admin_rows']), 8)
                .count($row['technical_rows']).PHP_EOL;
        }

        fwrite(STDOUT, $out);
    }
}
