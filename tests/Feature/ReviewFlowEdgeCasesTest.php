<?php

namespace Tests\Feature;

use App\Models\ApplicationReview;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\User;
use App\Services\IspoReviewService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cabang alur tinjauan permohonan yang jarang dilalui namun mudah rusak diam-diam:
 * LSPro yang dikembalikan Tim Teknis, ISPO yang pemohonnya belum memilih ruang
 * lingkup, dan permohonan yang diteruskan ulang setelah tinjauan teknis selesai.
 */
class ReviewFlowEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technical;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
        Storage::fake('private');

        /*
         * Akun admin dan teknis memegang peran Sustain juga: sebagian kasus di
         * berkas ini menjalankan ISPO, yang kini milik tim Sustain.
         */
        $peran = [
            'admin' => ['admin_application', 'admin_sustain'],
            'technical' => ['technical', 'technical_sustain'],
            'client' => ['client'],
        ];

        foreach ($peran as $property => $codes) {
            $user = User::create([
                'name' => 'Uji '.$codes[0],
                'email' => $codes[0].'@uji.test',
                'password' => 'RahasiaKuat123',
                'is_active' => true,
            ]);
            $user->roles()->attach(Role::whereIn('code', $codes)->pluck('id'));
            $this->{$property} = $user;
        }
    }

    /**
     * Pada LSPro, Tim Teknis tidak menilai baris melainkan memverifikasi kajian
     * Admin. Mengembalikannya wajib disertai catatan, dan selama dikembalikan
     * kolom "Hasil Kajian Peninjau" pada Fr.7201 tidak boleh ikut tercetak.
     */
    public function test_tinjauan_lspro_hanya_dapat_dikembalikan_dengan_catatan(): void
    {
        $application = $this->applicationInTechnicalReview('SNI_LOKAL');

        $this->actingAs($this->technical)
            ->post(route('technical.reviews.save', $application), [
                'action_date' => now()->format('Y-m-d'),
                'sni_verification' => 'return',
            ])
            ->assertStatus(422);

        $this->actingAs($this->technical)
            ->post(route('technical.reviews.save', $application), [
                'action_date' => now()->format('Y-m-d'),
                'sni_verification' => 'return',
                'notes' => 'Laporan hasil uji belum dilampirkan.',
            ])
            ->assertRedirect();

        $review = ApplicationReview::where('application_id', $application->id)
            ->where('review_type', 'technical')
            ->firstOrFail();

        $this->assertSame('in_progress', $review->status);
    }

    /**
     * Bila pemohon ISPO belum memilih ruang lingkup, formulir FrO.7204 tetap
     * harus terbuka dengan seluruh kelompok checklist — bukan kosong tanpa
     * penjelasan sehingga peninjau tidak bisa bekerja.
     */
    public function test_formulir_ispo_tetap_terbuka_tanpa_ruang_lingkup_pemohon(): void
    {
        $application = $this->applicationInTechnicalReview('ISPO');
        $ispo = app(IspoReviewService::class);

        $this->assertSame(
            count(config('review.ispo.document_groups')),
            count($ispo->documentGroups($application)),
            'Tanpa pilihan ruang lingkup, seluruh kelompok checklist harus ditampilkan.'
        );

        $this->actingAs($this->technical)
            ->get(route('technical.reviews.show', $application))
            ->assertOk();
    }

    /**
     * Dikembalikan ke Admin lalu diteruskan ulang: penanda selesai pada tinjauan
     * teknis dibuka kembali sehingga Tim Teknis wajib meninjau ulang.
     */
    public function test_diteruskan_ulang_membuka_kembali_tinjauan_teknis(): void
    {
        $application = $this->applicationInTechnicalReview('ISO9001');

        $this->actingAs($this->technical)->post(route('technical.reviews.save', $application), [
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $this->actingAs($this->technical)->post(route('technical.reviews.return-admin', $application))->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('internal.applications.forward-technical', $application->refresh()))
            ->assertRedirect();

        $this->assertNull(
            ApplicationReview::where('application_id', $application->id)
                ->where('review_type', 'technical')
                ->value('completed_at'),
            'Tinjauan teknis lama masih dianggap selesai setelah diteruskan ulang.'
        );
    }

    /**
     * Keputusan sekarang milik Tim Teknis: Admin tidak lagi punya jalurnya.
     */
    public function test_admin_tidak_punya_rute_keputusan(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('internal.applications.approve'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('internal.applications.reject'));
    }

    private function applicationInTechnicalReview(string $schemeCode): CertificationApplication
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->firstOrFail();

        $application = CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => 'admin_review',
            'current_step' => 'admin_review',
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'EDGE/'.$schemeCode,
            'order_date' => today(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('internal.applications.review', $application), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('internal.applications.forward-technical', $application))
            ->assertRedirect();

        return $application->refresh();
    }
}
