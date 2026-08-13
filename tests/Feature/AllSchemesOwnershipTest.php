<?php

namespace Tests\Feature;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\PortalNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\PortalNotificationService;
use App\Services\SchemeOwnershipService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sapuan kepemilikan skema untuk SELURUH katalog, bukan satu-dua contoh.
 *
 * Bedanya dengan SchemeOwnershipTest: berkas itu menguji perilaku pembagian
 * secara mendalam pada ISPO lawan ISO9001. Berkas ini menelusuri tiap skema
 * yang benar-benar ada di katalog, sehingga skema yang ditambahkan kemudian —
 * apalagi bila membawa review_template baru — otomatis ikut terjaga tanpa perlu
 * ada yang ingat menambahkan kasusnya di sini.
 */
class AllSchemesOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kepemilikan yang DIHARAPKAN, ditulis lugas per kode skema.
     *
     * Sengaja tidak dibaca dari config/scheme_ownership.php. Menurunkan harapan
     * dari sumber yang sedang diuji membuat test memeriksa dirinya sendiri:
     * config diubah, harapannya ikut berubah, test tetap hijau padahal
     * pembagiannya sudah bergeser. Tabel ini adalah keputusan bisnisnya —
     * ISPO milik tim Sustain, sisanya milik Admin Permohonan dan Tim Teknis —
     * dan hanya boleh berubah bila keputusan itu memang berubah.
     *
     * @var array<string, array{admin: string, technical: string}>
     */
    private const PEMILIK_DIHARAPKAN = [
        'ISO9001' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO14001' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO45001' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO27001' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO20000' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO37001' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO37301' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISO22000' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'HACCP' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'SNI' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'SNI_LOKAL' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'SNI_IMPORT' => ['admin' => 'admin_application', 'technical' => 'technical'],
        'ISPO' => ['admin' => 'admin_sustain', 'technical' => 'technical_sustain'],
    ];

    /**
     * Peran pemilik menurut config, dipisah per jenis ('admin' / 'technical').
     *
     * Dibaca dari kunci config, bukan ditebak dari awalan nama role: pembagian
     * baru dengan penamaan lain tetap terjaring benar.
     *
     * @return array<int, string>
     */
    private function peranPemilik(?string $jenis = null): array
    {
        $baris = [(array) config('scheme_ownership.default')];

        foreach ((array) config('scheme_ownership.by_template') as $pemilik) {
            $baris[] = (array) $pemilik;
        }

        $kode = [];
        foreach ($baris as $pemilik) {
            foreach ($pemilik as $kunci => $role) {
                if ($jenis === null || $kunci === $jenis) {
                    $kode[] = $role;
                }
            }
        }

        return array_values(array_unique($kode));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    /**
     * @param  array<int, string>  $roleCodes
     */
    private function user(array $roleCodes): User
    {
        $user = User::create([
            'name' => implode('+', $roleCodes).' '.Str::random(3),
            'email' => Str::random(10).'@example.test',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::whereIn('code', $roleCodes)->pluck('id'));

        return $user;
    }

    private function order(CertificationScheme $scheme, string $status): CertificationApplication
    {
        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $this->user(['client'])->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT '.$scheme->code,
            'contact_email' => 'kontak@uji.test',
            'order_number' => $scheme->code.'-'.Str::random(6),
            'order_date' => today(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Katalog dan tabel harapan harus cocok persis.
     *
     * Penjaga terpenting di berkas ini: skema baru yang ditambahkan ke katalog
     * memaksa seseorang memutuskan siapa pemiliknya dan menuliskannya di sini,
     * bukan diam-diam jatuh ke pemilik bawaan tanpa ada yang menimbang.
     */
    public function test_tabel_harapan_mencakup_persis_katalog(): void
    {
        $kodeKatalog = CertificationScheme::orderBy('code')->pluck('code')->all();
        $kodeHarapan = array_keys(self::PEMILIK_DIHARAPKAN);
        sort($kodeHarapan);

        $this->assertSame($kodeKatalog, $kodeHarapan,
            'Katalog skema dan tabel PEMILIK_DIHARAPKAN tidak cocok. '
            .'Bila ada skema baru, tentukan pemiliknya lalu daftarkan di tabel itu.');
    }

    /**
     * Pemetaan nyata harus sama dengan tabel harapan.
     *
     * Inilah yang menangkap perubahan config yang tidak disengaja: bila
     * config/scheme_ownership.php digeser, pemetaan nyatanya berubah sedangkan
     * tabel harapan tidak, dan test ini gagal menyebut skema mana yang bergeser.
     */
    public function test_pemetaan_nyata_sama_dengan_tabel_harapan(): void
    {
        $ownership = app(SchemeOwnershipService::class);
        $peranAda = Role::pluck('code')->all();

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $harapan = self::PEMILIK_DIHARAPKAN[$scheme->code];

            $this->assertSame($harapan['admin'], $ownership->adminRoleFor($scheme->review_template),
                "Pemilik admin skema {$scheme->code} bergeser dari yang diharapkan.");
            $this->assertSame($harapan['technical'], $ownership->technicalRoleFor($scheme->review_template),
                "Pemilik teknis skema {$scheme->code} bergeser dari yang diharapkan.");

            foreach ($harapan as $kode) {
                $this->assertContains($kode, $peranAda,
                    "Skema {$scheme->code} menunjuk role '{$kode}' yang tidak ada di tabel roles.");

                // Disebut pemilik saja tidak cukup — handles() harus mengakuinya.
                $this->assertTrue($ownership->handles($this->user([$kode]), $scheme),
                    "Role {$kode} diharapkan memiliki {$scheme->code} tetapi handles() menolaknya.");
            }
        }
    }

    /**
     * Gabungan cakupan seluruh peran pemilik menutupi seluruh katalog: tidak ada
     * skema yang luput, dan tidak ada peran yang mengaku memiliki template yang
     * tidak dipakai skema mana pun.
     */
    public function test_cakupan_para_pemilik_menutupi_seluruh_katalog(): void
    {
        $ownership = app(SchemeOwnershipService::class);
        $templateKatalog = CertificationScheme::distinct()->pluck('review_template')->filter()->sort()->values()->all();

        $tercakup = [];
        foreach ($this->peranPemilik() as $kode) {
            $dimiliki = $ownership->templatesOwnedBy($this->user([$kode]));
            $this->assertIsArray($dimiliki, "Role pemilik {$kode} seharusnya tersaring, bukan melihat seluruhnya.");
            $tercakup = array_merge($tercakup, $dimiliki);
        }

        $tercakup = array_values(array_unique($tercakup));
        sort($tercakup);

        $this->assertSame($templateKatalog, $tercakup,
            'Ada template skema yang tidak dimiliki peran mana pun, atau sebaliknya.');
    }

    /**
     * Untuk tiap skema: pemiliknya bisa membuka, tim seberang ditolak 403.
     *
     * Inilah pagar sesungguhnya — menyaring daftar saja meninggalkan lubang URL
     * langsung, dan lubang itu harus tertutup pada setiap skema, bukan hanya ISPO.
     */
    public function test_setiap_skema_hanya_dapat_dibuka_tim_pemiliknya(): void
    {
        $ownership = app(SchemeOwnershipService::class);

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $order = $this->order($scheme, 'admin_review');
            $adminPemilik = self::PEMILIK_DIHARAPKAN[$scheme->code]['admin'];

            foreach ($this->peranPemilik('admin') as $kode) {
                $harusnya = $kode === $adminPemilik ? 200 : 403;

                $this->actingAs($this->user([$kode]))
                    ->get(route('internal.applications.show', $order))
                    ->assertStatus($harusnya, "Skema {$scheme->code}: role {$kode} seharusnya {$harusnya} pada halaman admin.");
            }

            $orderTeknis = $this->order($scheme, 'technical_review');
            $teknisPemilik = self::PEMILIK_DIHARAPKAN[$scheme->code]['technical'];

            foreach ($this->peranPemilik('technical') as $kode) {
                $harusnya = $kode === $teknisPemilik ? 200 : 403;

                $this->actingAs($this->user([$kode]))
                    ->get(route('technical.reviews.show', $orderTeknis))
                    ->assertStatus($harusnya, "Skema {$scheme->code}: role {$kode} seharusnya {$harusnya} pada tinjauan teknis.");
            }
        }
    }

    /**
     * Antrean tiap tim memuat persis skema miliknya — tidak kurang, tidak lebih.
     */
    public function test_antrean_tiap_tim_memuat_persis_skema_miliknya(): void
    {
        $ownership = app(SchemeOwnershipService::class);
        $schemes = CertificationScheme::orderBy('sort_order')->get();

        $orders = [];
        foreach ($schemes as $scheme) {
            $orders[$scheme->code] = $this->order($scheme, 'admin_review');
        }

        foreach ([$ownership->adminRoleFor(null), $ownership->adminRoleFor('ispo')] as $kodeAdmin) {
            $html = $this->actingAs($this->user([$kodeAdmin]))
                ->get(route('internal.applications.index'))
                ->assertOk()
                ->getContent();

            foreach ($schemes as $scheme) {
                $nomor = $orders[$scheme->code]->order_number;
                $miliknya = self::PEMILIK_DIHARAPKAN[$scheme->code]['admin'] === $kodeAdmin;

                if ($miliknya) {
                    $this->assertStringContainsString($nomor, $html,
                        "Antrean {$kodeAdmin} seharusnya memuat {$scheme->code}.");
                } else {
                    $this->assertStringNotContainsString($nomor, $html,
                        "Antrean {$kodeAdmin} seharusnya TIDAK memuat {$scheme->code}.");
                }
            }
        }
    }

    /**
     * Notifikasi tiap skema mendarat di tim pemiliknya saja.
     */
    public function test_notifikasi_tiap_skema_mendarat_di_pemiliknya(): void
    {
        $ownership = app(SchemeOwnershipService::class);
        $notifications = app(PortalNotificationService::class);

        $akun = [];
        foreach ($this->peranPemilik() as $kode) {
            $akun[$kode] = $this->user([$kode]);
        }

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $order = $this->order($scheme, 'admin_review');

            foreach (['admin', 'technical'] as $jenis) {
                $tipe = 'uji_'.$jenis.'_'.strtolower($scheme->code);
                $pemilik = self::PEMILIK_DIHARAPKAN[$scheme->code][$jenis];

                $notifications->sendToSchemeOwner($order, $jenis, $tipe, 'Judul', 'Pesan');

                foreach ($akun as $kode => $user) {
                    $jumlah = PortalNotification::where('user_id', $user->id)->where('type', $tipe)->count();
                    $harusnya = $kode === $pemilik ? 1 : 0;

                    $this->assertSame($harusnya, $jumlah,
                        "Skema {$scheme->code} ({$jenis}): role {$kode} menerima {$jumlah}, seharusnya {$harusnya}.");
                }
            }
        }
    }

    /**
     * Langkah workflow tiap skema menyebut peran pemiliknya.
     *
     * Ini yang menentukan siapa penanggung jawab langkah pada monitoring; bila
     * salah, monitoring menunjuk tim yang bahkan tidak bisa membuka ordernya.
     */
    public function test_langkah_workflow_tiap_skema_menyebut_pemiliknya(): void
    {
        $ownership = app(SchemeOwnershipService::class);

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $template = WorkflowTemplate::where('certification_scheme_id', $scheme->id)->first();
            $this->assertNotNull($template, "Skema {$scheme->code} tidak punya WorkflowTemplate.");

            $steps = $template->steps()->pluck('role_code', 'code');

            $this->assertSame(
                self::PEMILIK_DIHARAPKAN[$scheme->code]['admin'],
                $steps['admin_review'] ?? null,
                "Langkah admin_review skema {$scheme->code} menunjuk peran yang salah."
            );

            foreach (['certificate_review', 'final_certificate'] as $kode) {
                $this->assertSame(
                    self::PEMILIK_DIHARAPKAN[$scheme->code]['technical'],
                    $steps[$kode] ?? null,
                    "Langkah {$kode} skema {$scheme->code} menunjuk peran yang salah."
                );
            }
        }
    }

    /**
     * Akun berperan ganda melihat seluruh katalog, dan tetap menerima satu
     * notifikasi per order — bukan satu per peran yang dipegangnya.
     */
    public function test_akun_berperan_ganda_mencakup_seluruh_katalog(): void
    {
        $ganda = $this->user(['admin_application', 'admin_sustain']);
        $notifications = app(PortalNotificationService::class);
        $schemes = CertificationScheme::orderBy('sort_order')->get();

        $html = $this->actingAs($ganda)
            ->get(route('internal.applications.index'))
            ->assertOk()
            ->getContent();

        foreach ($schemes as $scheme) {
            $order = $this->order($scheme, 'admin_review');

            $this->actingAs($ganda)
                ->get(route('internal.applications.show', $order))
                ->assertOk("Akun dua role seharusnya bisa membuka {$scheme->code}.");

            $tipe = 'ganda_'.strtolower($scheme->code);
            $notifications->sendToSchemeOwner($order, 'admin', $tipe, 'Judul', 'Pesan');

            $this->assertSame(1,
                PortalNotification::where('user_id', $ganda->id)->where('type', $tipe)->count(),
                "Skema {$scheme->code}: akun dua role seharusnya menerima tepat satu notifikasi."
            );
        }

        // Daftar dibaca sebelum order dibuat, jadi cukup pastikan halamannya hidup.
        $this->assertStringContainsString('Review Permohonan', $html);
    }

    /**
     * Superadmin tetap menembus seluruh skema, apa pun pembagiannya.
     */
    public function test_superadmin_menembus_seluruh_skema(): void
    {
        $superadmin = $this->user(['superadmin']);

        foreach (CertificationScheme::orderBy('sort_order')->get() as $scheme) {
            $this->actingAs($superadmin)
                ->get(route('internal.applications.show', $this->order($scheme, 'admin_review')))
                ->assertOk("Superadmin seharusnya bisa membuka {$scheme->code}.");
        }
    }
}
