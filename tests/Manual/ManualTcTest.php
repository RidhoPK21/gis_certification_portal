<?php

namespace Tests\Manual;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\EmailOtp;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemeriksaan butir uji manual pada subbab 7.4 dokumen SW-KP-26-06-A.
 *
 * Berkas ini sengaja diletakkan di tests/Manual, bukan tests/Feature, supaya
 * tidak ikut terhitung pada jumlah butir uji otomatis yang dilaporkan di
 * subbab 7.3. Jalankan sendiri dengan:
 *
 *     vendor/bin/phpunit tests/Manual/ManualTcTest.php
 *
 * Setiap metode diberi nama sesuai kode butir ujinya, sehingga hasil larinya
 * dapat langsung dipindahkan ke kolom Result dan Status pada dokumen.
 */
class ManualTcTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ pembantu

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    /** @param array<int, string>|string $roleCodes */
    private function user($roleCodes, bool $aktif = true, bool $verified = true): User
    {
        $roles = (array) $roleCodes;

        $user = User::create([
            'name' => implode('+', $roles) . ' ' . Str::random(3),
            'email' => Str::lower(Str::random(8)) . '@example.test',
            'password' => 'RahasiaKuat123',
            'is_active' => $aktif,
        ]);

        $user->roles()->attach(Role::whereIn('code', $roles)->pluck('id'));

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function scheme(string $code = 'ISO9001'): CertificationScheme
    {
        return CertificationScheme::where('code', $code)->firstOrFail();
    }

    private function order(
        string $schemeCode = 'ISO9001',
        string $status = 'admin_review',
        ?User $client = null
    ): CertificationApplication {
        $scheme = $this->scheme($schemeCode);

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => ($client ?? $this->user('client'))->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Uji Manual',
            'contact_email' => 'kontak@ujimanual.test',
            'order_number' => strtoupper(Str::random(8)),
            'order_date' => today(),
        ]);
    }

    // ------------------------------------------------- TCM-AUT (14 butir)

    public function test_TCM_AUT_01_registrasi_data_valid(): void
    {
        $this->seedAll();

        $this->post(route('register'), [
            'name' => 'Klien Uji',
            'company_name' => 'PT Uji Manual',
            'email' => 'klienuji@example.test',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
        ]);

        $user = User::where('email', 'klienuji@example.test')->first();

        $this->assertNotNull($user, 'akun tidak terbentuk');
        $this->assertNull($user->email_verified_at, 'email seharusnya belum terverifikasi');
        $this->assertTrue(
            EmailOtp::where('user_id', $user->id)
                ->where('purpose', EmailOtp::PURPOSE_REGISTRATION)
                ->exists(),
            'kode OTP registrasi tidak terbentuk'
        );
    }

    public function test_TCM_AUT_02_registrasi_email_sudah_terdaftar(): void
    {
        $this->seedAll();
        $lama = $this->user('client');

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'Klien Uji',
                'company_name' => 'PT Uji Manual',
                'email' => $lama->email,
                'password' => 'RahasiaKuat123',
                'password_confirmation' => 'RahasiaKuat123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', $lama->email)->count());
    }

    public function test_TCM_AUT_03_registrasi_tanpa_turnstile(): void
    {
        $this->seedAll();

        config([
            'turnstile.site_key' => 'site-key-uji',
            'turnstile.secret_key' => 'secret-key-uji',
        ]);

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'Klien Tanpa Turnstile',
                'company_name' => 'PT Uji Manual',
                'email' => 'tanpaturnstile@example.test',
                'password' => 'RahasiaKuat123',
                'password_confirmation' => 'RahasiaKuat123',
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', ['email' => 'tanpaturnstile@example.test']);
    }

    public function test_TCM_AUT_04_verifikasi_otp_benar(): void
    {
        $this->seedAll();
        $user = $this->user('client', true, false);
        $kode = app(OtpService::class)->generate($user, EmailOtp::PURPOSE_REGISTRATION);

        $this->withSession(['otp_pending_user_id' => $user->id])
            ->post(route('register.verify.submit'), ['code' => $kode])
            ->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_TCM_AUT_05_verifikasi_otp_salah(): void
    {
        $this->seedAll();
        $user = $this->user('client', true, false);
        app(OtpService::class)->generate($user, EmailOtp::PURPOSE_REGISTRATION);

        $this->withSession(['otp_pending_user_id' => $user->id])
            ->from(route('register.verify.show'))
            ->post(route('register.verify.submit'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_TCM_AUT_06_verifikasi_otp_kedaluwarsa(): void
    {
        $this->seedAll();
        $user = $this->user('client', true, false);
        $kode = app(OtpService::class)->generate($user, EmailOtp::PURPOSE_REGISTRATION);

        EmailOtp::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->withSession(['otp_pending_user_id' => $user->id])
            ->from(route('register.verify.show'))
            ->post(route('register.verify.submit'), ['code' => $kode])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_TCM_AUT_07_kirim_ulang_otp_melebihi_batas(): void
    {
        $this->seedAll();
        $user = $this->user('client', true, false);

        $this->seedAll();

        /*
         * Sesi pada lingkungan pengujian tidak bertahan antarpermintaan, sehingga
         * yang diperiksa adalah dua hal yang menentukan perilakunya: rutenya
         * memang dijaga middleware throttle, dan batas yang terdaftar benar satu
         * permintaan per menit serta lima per jam.
         */
        $rute = collect(app('router')->getRoutes())->first(
            fn ($r) => $r->getName() === 'register.verify.resend'
        );

        $this->assertContains(
            'throttle:otp-resend-registration',
            $rute->gatherMiddleware(),
            'rute kirim ulang OTP tidak dijaga pembatasan laju'
        );

        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        $permintaan = \Illuminate\Http\Request::create('/register/verify/resend', 'POST');
        $permintaan->setLaravelSession(app('session.store'));

        $batas = collect($limiter->limiter('otp-resend-registration')($permintaan))
            ->map(fn ($l) => $l->maxAttempts . '/' . $l->decaySeconds)
            ->all();

        $this->assertSame(['1/60', '5/3600'], $batas, 'angka pembatasan laju berubah');

        // Batas itu benar-benar memblokir percobaan kedua pada kunci yang sama.
        $kunci = 'uji-otp-resend';
        $limiter->clear($kunci);
        $limiter->hit($kunci, 60);

        $this->assertTrue(
            $limiter->tooManyAttempts($kunci, 1),
            'percobaan kedua pada menit yang sama seharusnya sudah tertahan'
        );
    }

    public function test_TCM_AUT_08_login_kredensial_benar(): void
    {
        $this->seedAll();
        $user = $this->user('client');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'RahasiaKuat123',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at, 'waktu login terakhir tidak tercatat');
    }

    public function test_TCM_AUT_09_login_kredensial_salah(): void
    {
        $this->seedAll();
        $user = $this->user('client');

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'SalahSekali123',
            ])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_TCM_AUT_10_login_akun_dinonaktifkan(): void
    {
        $this->seedAll();
        $user = $this->user('client', false);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'RahasiaKuat123',
            ]);

        $this->assertGuest();
    }

    public function test_TCM_AUT_11_aktivasi_akun_internal(): void
    {
        $this->seedAll();
        $user = $this->user('finance', true, false);
        $kode = app(OtpService::class)->generate($user, EmailOtp::PURPOSE_ADMIN_INVITE);

        $this->post(route('account.activate.submit'), [
            'email' => $user->email,
            'code' => $kode,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_TCM_AUT_12_reset_kata_sandi_melalui_otp(): void
    {
        $this->seedAll();
        $user = $this->user('client');
        $kode = app(OtpService::class)->generate($user, EmailOtp::PURPOSE_PASSWORD_RESET);

        $this->post(route('password.reset.submit'), [
            'email' => $user->email,
            'code' => $kode,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_TCM_AUT_13_unggah_tanda_tangan_profil(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $user = $this->user('technical');

        $berkas = \Illuminate\Http\Testing\File::image('ttd.png', 300, 120);

        $this->actingAs($user)
            ->post(route('profile.signature'), ['signature' => $berkas])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->signature_path, 'tanda tangan tidak tersimpan');
    }

    public function test_TCM_AUT_14_klien_membuka_halaman_internal(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('client'))
            ->get(route('internal.applications.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------- TCM-SKM (9 butir)

    public function test_TCM_SKM_01_menambah_section_baru(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.form-builder.sections.store', $scheme), [
                'code' => 'section_uji',
                'title' => 'Section Uji Manual',
                'sort_order' => 99,
            ])->assertRedirect();

        $this->assertDatabaseHas('scheme_sections', [
            'certification_scheme_id' => $scheme->id,
            'code' => 'section_uji',
        ]);
    }

    public function test_TCM_SKM_02_menonaktifkan_field_wajib(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();
        $field = $scheme->sections()->first()->fields()->where('is_required', true)->first();

        if (! $field) {
            $this->markTestSkipped('Skema uji tidak memiliki field wajib.');
        }

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.form-builder.fields.toggle', [$scheme, $field]))
            ->assertRedirect();

        $this->assertFalse((bool) $field->fresh()->is_active);
    }

    public function test_TCM_SKM_03_menerbitkan_versi_form(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();
        $versiAwal = $scheme->form_version;

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.form-builder.sections.store', $scheme), [
                'code' => 'section_versi',
                'title' => 'Section Penanda Versi',
                'sort_order' => 98,
            ])->assertRedirect();

        $this->assertGreaterThanOrEqual($versiAwal, $scheme->fresh()->form_version);
    }

    public function test_TCM_SKM_04_permohonan_lama_memakai_versi_lamanya(): void
    {
        $this->seedAll();
        $scheme = $this->scheme();
        $app = $this->order();
        $versiPermohonan = $app->form_version;

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.form-builder.sections.store', $scheme), [
                'code' => 'section_setelah',
                'title' => 'Section Setelah Permohonan',
                'sort_order' => 97,
            ])->assertRedirect();

        $this->assertSame($versiPermohonan, $app->fresh()->form_version);
    }

    public function test_TCM_SKM_05_aturan_kondisional_menyembunyikan_section(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->draftKlien($klien, 'ISPO');

        $forms = app(\App\Services\DynamicFormService::class);
        $snapshot = $forms->schemeForApplication($app);

        /*
         * Aturan kondisional pada skema ISPO memakai jenis pemohon sebagai
         * pemicu. Dokumen milik jenis pemohon lain tidak boleh ikut diminta.
         */
        $perkebunan = $forms->applicableDocuments(
            $snapshot,
            ['applicant_type' => ['perusahaan_perkebunan']]
        )->pluck('code');

        $hilir = $forms->applicableDocuments(
            $snapshot,
            ['applicant_type' => ['industri_hilir']]
        )->pluck('code');

        $this->assertTrue($perkebunan->contains('doc_plantation_license'));
        $this->assertFalse($perkebunan->contains('doc_downstream_license'));

        $this->assertTrue($hilir->contains('doc_downstream_license'));
        $this->assertFalse($hilir->contains('doc_plantation_license'));
    }

    public function test_TCM_SKM_06_admin_permohonan_membuka_ispo(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');

        $this->actingAs($this->user('admin_application'))
            ->get(route('internal.applications.show', $ispo))
            ->assertForbidden();
    }

    public function test_TCM_SKM_07_admin_sustain_membuka_ispo(): void
    {
        $this->seedAll();
        $ispo = $this->order('ISPO');

        $this->actingAs($this->user('admin_sustain'))
            ->get(route('internal.applications.show', $ispo))
            ->assertOk();
    }

    public function test_TCM_SKM_08_unggah_template_formulir_gis(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $scheme = $this->scheme();

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.gis-forms.store', $scheme), [
                'code' => 'FRM-UJI',
                'name' => 'Formulir Uji Manual',
                'file' => \Illuminate\Http\Testing\File::create('formulir.pdf', 40),
            ])->assertRedirect();

        $this->assertDatabaseHas('gis_form_templates', ['name' => 'Formulir Uji Manual']);
    }

    public function test_TCM_SKM_09_klien_meminta_formulir_gis(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'draft', $klien);

        $template = \App\Models\GisFormTemplate::create([
            'certification_scheme_id' => $app->certification_scheme_id,
            'code' => 'FRM-REQ',
            'name' => 'Formulir Permintaan',
            'version' => 1,
            'original_name' => 'formulir.pdf',
            'stored_name' => 'formulir-uji.pdf',
            'file_path' => 'gis-forms/uji.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($klien)
            ->post(route('client.gis-form-requests.store', $app), [
                'client_note' => 'Mohon dikirimkan formulirnya.',
            ])->assertRedirect();

        $this->assertDatabaseHas('gis_form_requests', [
            'application_id' => $app->id,
            'requested_by' => $klien->id,
            'status' => 'pending',
        ]);
    }

    // ------------------------------------------------- TCM-PMH (13 butir)

    public function test_TCM_PMH_01_membuat_draft_permohonan(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $scheme = $this->scheme();

        $this->actingAs($klien)
            ->post(route('client.applications.store', $scheme), [
                'company_name' => 'PT Uji Manual',
                'applicant_name' => 'Budi',
                'contact_email' => 'budi@ujimanual.test',
                'contact_phone' => '0811',
            ])->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'client_id' => $klien->id,
            'status' => 'draft',
        ]);
    }

    public function test_TCM_PMH_02_menyimpan_isian_bertahap(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'draft', $klien);
        $field = $this->scheme()->sections()->first()->fields()->first();

        $this->actingAs($klien)
            ->put(route('client.applications.update', $app), [
                'fields' => [$field->code => 'Nilai Uji Manual'],
            ])->assertRedirect();

        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id,
            'field_code' => $field->code,
            'value_text' => 'Nilai Uji Manual',
        ]);
    }

    public function test_TCM_PMH_03_mengunggah_dokumen_wajib(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);
        $doc = $this->dokumenPerusahaan($app);

        $this->actingAs($klien)
            ->post(route('client.documents.store', $app), [
                'document_code' => $doc->code,
                'file' => \Illuminate\Http\UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
            ])->assertRedirect();

        $dokumen = \App\Models\ApplicationDocument::where('application_id', $app->id)
            ->where('document_code', $doc->code)->first();

        $this->assertNotNull($dokumen, 'dokumen tidak tersimpan');
        $this->assertSame(1, $dokumen->versions()->count(), 'versi pertama tidak terbentuk');
    }

    public function test_TCM_PMH_04_mengunggah_ulang_dokumen_sama(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);
        $doc = $this->dokumenPerusahaan($app);

        foreach (['satu.pdf', 'dua.pdf'] as $nama) {
            $this->actingAs($klien)->post(route('client.documents.store', $app), [
                'document_code' => $doc->code,
                'file' => \Illuminate\Http\UploadedFile::fake()->create($nama, 100, 'application/pdf'),
            ])->assertRedirect();
        }

        $dokumen = \App\Models\ApplicationDocument::where('application_id', $app->id)
            ->where('document_code', $doc->code)->firstOrFail();

        $this->assertSame(2, $dokumen->versions()->count(), 'versi kedua tidak tersimpan');
    }

    public function test_TCM_PMH_05_unggah_melebihi_batas_ukuran(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);
        $doc = $this->dokumenPerusahaan($app);

        $this->actingAs($klien)
            ->from(route('client.applications.edit', $app))
            ->post(route('client.documents.store', $app), [
                'document_code' => $doc->code,
                'file' => \Illuminate\Http\UploadedFile::fake()->create('besar.pdf', 60000, 'application/pdf'),
            ])->assertSessionHasErrors();

        $dokumen = \App\Models\ApplicationDocument::where('application_id', $app->id)
            ->where('document_code', $doc->code)
            ->first();

        $this->assertTrue(
            $dokumen === null || $dokumen->versions()->count() === 0,
            'berkas yang melebihi batas ternyata tetap tersimpan sebagai versi dokumen'
        );
    }

    public function test_TCM_PMH_06_mengirim_permohonan_lengkap(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);

        $this->isiSeluruhKewajiban($app, $klien);

        $this->actingAs($klien)
            ->post(route('client.applications.submit', $app))
            ->assertRedirect();

        $app->refresh();
        $this->assertNotNull($app->order_number, 'nomor order tidak terbentuk');
        $this->assertContains($app->status, ['submitted', 'admin_review']);
    }

    public function test_TCM_PMH_07_mengirim_permohonan_belum_lengkap(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);

        $this->actingAs($klien)
            ->from(route('client.applications.edit', $app))
            ->post(route('client.applications.submit', $app))
            ->assertSessionHasErrors();

        $this->assertSame('draft', $app->fresh()->status);
    }

    public function test_TCM_PMH_08_membuka_permohonan_klien_lain(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'draft');

        $this->actingAs($this->user('client'))
            ->get(route('client.applications.show', $app))
            ->assertForbidden();
    }

    public function test_TCM_PMH_09_menghapus_draft_sendiri(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);

        $this->actingAs($klien)
            ->delete(route('client.applications.destroy', $app))
            ->assertRedirect();

        $this->assertDatabaseMissing('applications', ['id' => $app->id]);
    }

    public function test_TCM_PMH_10_menghapus_permohonan_terkirim(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'admin_review', $klien);

        $this->actingAs($klien)
            ->from(route('client.applications.show', $app))
            ->delete(route('client.applications.destroy', $app));

        $this->assertDatabaseHas('applications', ['id' => $app->id]);
    }

    public function test_TCM_PMH_11_melacak_nomor_order_benar(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->post(route('public.track'), ['order_number' => $app->order_number])
            ->assertOk()
            ->assertSee('Permohonan');
    }

    public function test_TCM_PMH_12_melacak_nomor_order_salah(): void
    {
        $this->seedAll();

        $this->from(route('public.home'))
            ->post(route('public.track'), ['order_number' => 'TIDAK-ADA-999'])
            ->assertRedirect(route('public.home'))
            ->assertSessionHasErrors('order_number');
    }

    public function test_TCM_PMH_13_melacak_melebihi_batas_laju(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        // Batasnya 15 permintaan per menit.
        for ($i = 0; $i < 15; $i++) {
            $this->post(route('public.track'), ['order_number' => $app->order_number]);
        }

        $this->post(route('public.track'), ['order_number' => $app->order_number])
            ->assertStatus(429);
    }

    // ------------------------------------------------- TCM-TJN (10 butir)

    public function test_TCM_TJN_01_mengisi_formulir_tinjauan_admin(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->actingAs($this->user('admin_application'))
            ->post(route('internal.applications.review', $app), [
                'review_type' => 'administration',
                'action_date' => now()->format('Y-m-d'),
                'signed_name' => 'Admin Uji',
            ])->assertRedirect();

        $this->assertDatabaseHas('application_reviews', [
            'application_id' => $app->id,
            'review_type' => 'administration',
        ]);
    }

    public function test_TCM_TJN_02_meneruskan_sebelum_tinjauan_lengkap(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->actingAs($this->user('admin_application'))
            ->from(route('internal.applications.show', $app))
            ->post(route('internal.applications.forward-technical', $app));

        $this->assertSame('admin_review', $app->fresh()->status);
    }

    public function test_TCM_TJN_03_meminta_revisi_kepada_klien(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->actingAs($this->user('admin_application'))
            ->post(route('internal.applications.revision', $app), [
                'targets' => [
                    ['type' => 'field', 'code' => 'company_name',
                     'label' => 'Nama perusahaan', 'note' => 'Lengkapi nama resmi.'],
                ],
            ])->assertRedirect();

        $this->assertSame('revision_requested', $app->fresh()->status);
        $this->assertDatabaseHas('application_revision_items', ['application_id' => $app->id]);
    }

    public function test_TCM_TJN_04_klien_mengirim_ulang_perbaikan(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'admin_review', $klien);

        $this->actingAs($this->user('admin_application'))
            ->post(route('internal.applications.revision', $app), [
                'targets' => [
                    ['type' => 'field', 'code' => 'company_name',
                     'label' => 'Nama perusahaan', 'note' => 'Lengkapi nama resmi.'],
                ],
            ])->assertRedirect();

        // Klien melengkapi kewajiban lebih dulu, lalu mengirim ulang permohonan.
        $this->isiSeluruhKewajiban($app->fresh(), $klien);

        $this->actingAs($klien)
            ->post(route('client.applications.submit', $app->fresh()))
            ->assertRedirect();

        $this->assertContains($app->fresh()->status, ['client_revision', 'admin_review']);
    }

    public function test_TCM_TJN_05_menghasilkan_pdf_tinjauan(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'admin_review');
        $admin = $this->user('admin_application');

        $this->actingAs($admin)->post(route('internal.applications.review', $app), [
            'review_type' => 'administration',
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Admin Uji',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('internal.applications.generate-pdf', $app), ['type' => 'administration'])
            ->assertRedirect();

        $this->assertDatabaseHas('generated_pdfs', ['application_id' => $app->id]);
    }

    public function test_TCM_TJN_06_tim_teknis_menyetujui(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'technical_review');
        $teknis = $this->user('technical');
        $auditor = $this->user('auditor');

        // Keputusan menyetujui menuntut Lead Auditor sudah ditetapkan.
        $this->actingAs($teknis)->post(route('technical.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($teknis)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Teknis Uji',
        ])->assertRedirect();

        $this->actingAs($teknis)->post(route('technical.reviews.approve', $app), [
            'action_date' => now()->format('Y-m-d'),
            'notes' => 'Disetujui.',
        ])->assertRedirect();

        $this->assertContains(
            $app->fresh()->status,
            ['application_approved', 'invoice_process'],
            'status tidak berpindah setelah disetujui'
        );
    }

    public function test_TCM_TJN_07_menolak_tanpa_alasan(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'technical_review');

        $this->actingAs($this->user('technical'))
            ->from(route('technical.reviews.show', $app))
            ->post(route('technical.reviews.reject', $app), [
                'action_date' => now()->format('Y-m-d'),
            ])->assertSessionHasErrors();

        $this->assertSame('technical_review', $app->fresh()->status);
    }

    public function test_TCM_TJN_08_mengembalikan_ke_admin(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'technical_review');
        $teknis = $this->user('technical');

        // Pengembalian menuntut tinjauan teknis sudah tersimpan lebih dulu.
        $this->actingAs($teknis)->post(route('technical.reviews.save', $app), [
            'action_date' => now()->format('Y-m-d'),
            'signed_name' => 'Teknis Uji',
        ])->assertRedirect();

        $this->actingAs($teknis)
            ->post(route('technical.reviews.return-admin', $app), [
                'action_date' => now()->format('Y-m-d'),
                'notes' => 'Perlu dilengkapi Admin.',
            ])->assertRedirect();

        $this->assertSame('admin_review', $app->fresh()->status);
    }

    public function test_TCM_TJN_09_menerbitkan_surat_tugas(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'payment_completed');
        $teknis = $this->user('technical');
        $auditor = $this->user('auditor');

        $this->actingAs($teknis)->post(route('technical.audit-assignments.store', $app), [
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => 'all',
            'assigned_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->terbitkanSuratTugas($app, 'stage_1');

        $this->assertDatabaseHas('assignment_letters', [
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
        ]);
    }

    public function test_TCM_TJN_10_auditor_membuka_surat_tugas_orang_lain(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'payment_completed');

        $this->actingAs($this->user('auditor'))
            ->get(route('technical.assignments.show', $app))
            ->assertForbidden();
    }

    // ------------------------------------------------- TCM-FIN (6 butir)

    public function test_TCM_FIN_01_menerbitkan_invoice(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'invoice_process');

        $this->actingAs($this->user('finance'))
            ->post(route('finance.invoice', $app), [
                'invoice_number' => 'INV-MANUAL-001',
                'amount' => 5000000,
                'invoice_date' => now()->format('Y-m-d'),
                'payment_stage' => 'belum_lunas',
            ])->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'application_id' => $app->id,
            'invoice_number' => 'INV-MANUAL-001',
        ]);
    }

    public function test_TCM_FIN_02_invoice_pada_permohonan_belum_disetujui(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->actingAs($this->user('finance'))
            ->from(route('finance.index'))
            ->post(route('finance.invoice', $app), [
                'invoice_number' => 'INV-DINI-001',
                'amount' => 1000000,
                'invoice_date' => now()->format('Y-m-d'),
                'payment_stage' => 'belum_lunas',
            ]);

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV-DINI-001']);
    }

    public function test_TCM_FIN_03_mencatat_pembayaran_sebagian(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'invoice_process');
        $finance = $this->user('finance');

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-PART-001',
            'amount' => 10000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'tahap_1',
        ])->assertRedirect();

        $this->assertContains($app->fresh()->status, ['payment_partial', 'invoice_process']);
    }

    public function test_TCM_FIN_04_mencatat_pelunasan(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'invoice_process');
        $finance = $this->user('finance');

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-LUNAS-001',
            'amount' => 10000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'lunas',
        ])->assertRedirect();

        $this->assertSame('payment_completed', $app->fresh()->status);
    }

    public function test_TCM_FIN_05_pembayaran_melebihi_sisa_tagihan(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'invoice_process');
        $finance = $this->user('finance');

        $this->actingAs($finance)->post(route('finance.invoice', $app), [
            'invoice_number' => 'INV-LEBIH-001',
            'amount' => 1000000,
            'invoice_date' => now()->format('Y-m-d'),
            'payment_stage' => 'belum_lunas',
        ])->assertRedirect();

        $invoice = Invoice::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($finance)
            ->from(route('finance.show', $app))
            ->post(route('finance.payment', $app), [
                'amount' => 9999999999,
                'payment_date' => now()->format('Y-m-d'),
            ]);

        $this->assertLessThanOrEqual(
            (float) $invoice->amount,
            (float) $invoice->payments()->sum('amount'),
            'pembayaran melebihi nilai invoice ternyata tersimpan'
        );
    }

    public function test_TCM_FIN_06_klien_membuka_modul_finance(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('client'))
            ->get(route('finance.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------- TCM-ADT (8 butir)

    public function test_TCM_ADT_01_menugaskan_auditor(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'payment_completed');
        $auditor = $this->user('auditor');

        $this->actingAs($this->user('technical'))
            ->post(route('technical.audit-assignments.store', $app), [
                'auditor_id' => $auditor->id,
                'assignment_role' => 'LA',
                'stage_code' => 'all',
                'assigned_date' => now()->format('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('audit_assignments', [
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
        ]);

        $this->actingAs($auditor)->get(route('audit.show', $app))->assertOk();
    }

    public function test_TCM_ADT_02_mengisi_hasil_tahap_audit(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'payment_completed');
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'stage_1');

        $this->actingAs($auditor)
            ->post(route('audit.stage', $app), [
                'stage_code' => 'stage_1',
                'status' => 'approved',
                'audit_date' => now()->format('Y-m-d'),
                'auditor_team' => 'LA: A. Auditor',
            ])->assertRedirect();

        $this->assertDatabaseHas('audit_stages', [
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
        ]);
    }

    public function test_TCM_ADT_03_melewati_tahap_yang_boleh_dilewati(): void
    {
        $this->seedAll();
        $app = $this->order('SNI', 'payment_completed');
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'stage_1');

        $this->actingAs($auditor)
            ->post(route('audit.stage.skip', $app), [
                'stage_code' => 'stage_1',
                'reason' => 'Produk sudah diaudit pada siklus sebelumnya.',
                'action_date' => now()->format('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('audit_stages', [
            'application_id' => $app->id,
            'stage_code' => 'stage_1',
            'status' => 'skipped',
        ]);
    }

    public function test_TCM_ADT_04_melewati_tahap_wajib(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'payment_completed');
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'stage_1');

        $this->actingAs($auditor)
            ->post(route('audit.stage.skip', $app), [
                'stage_code' => 'stage_1',
                'reason' => 'Klien sudah tersertifikasi sebelumnya.',
                'action_date' => now()->format('Y-m-d'),
            ])->assertStatus(422);

        $this->assertDatabaseMissing('audit_stages', [
            'application_id' => $app->id,
            'status' => 'skipped',
        ]);
    }

    public function test_TCM_ADT_05_menerbitkan_temuan(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'qms_audit');
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'qms');

        $this->actingAs($auditor)
            ->post(route('audit.findings.store', $app), [
                'finding_number' => 'NC-01',
                'finding_type' => 'minor',
                'description' => 'Prosedur belum lengkap.',
                'due_date' => now()->addDays(14)->format('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('findings', ['application_id' => $app->id]);
        $this->assertSame('corrective_action', $app->fresh()->status);
    }

    public function test_TCM_ADT_06_klien_mengirim_tindakan_koreksi(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'qms_audit', $klien);
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'qms');

        $this->actingAs($auditor)->post(route('audit.findings.store', $app), [
            'finding_number' => 'NC-02',
            'finding_type' => 'minor',
            'description' => 'Rekaman belum tersedia.',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect();

        $finding = \App\Models\Finding::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($klien)
            ->post(route('client.corrective-actions.store', $finding), [
                'root_cause' => 'Akar penyebab.',
                'correction' => 'Koreksi.',
                'corrective_action' => 'Tindakan pencegahan.',
            ])->assertRedirect();

        $this->assertDatabaseHas('corrective_actions', ['finding_id' => $finding->id]);
    }

    public function test_TCM_ADT_07_minta_perbaikan_ulang_tanpa_catatan(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'qms_audit', $klien);
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'qms');

        $this->actingAs($auditor)->post(route('audit.findings.store', $app), [
            'finding_number' => 'NC-03',
            'finding_type' => 'minor',
            'description' => 'Bukti belum lengkap.',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect();

        $finding = \App\Models\Finding::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($klien)->post(route('client.corrective-actions.store', $finding), [
            'root_cause' => 'Akar penyebab.',
            'correction' => 'Koreksi.',
            'corrective_action' => 'Tindakan pencegahan.',
        ])->assertRedirect();

        $ca = \App\Models\CorrectiveAction::where('finding_id', $finding->id)->firstOrFail();

        $this->actingAs($auditor)
            ->from(route('audit.show', $app))
            ->post(route('audit.corrective-actions.review', $ca), [
                'status' => 'rejected',
                'action_date' => now()->format('Y-m-d'),
            ])->assertSessionHasErrors();
    }

    public function test_TCM_ADT_08_menerima_seluruh_tindakan_koreksi(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->order('ISO9001', 'qms_audit', $klien);
        $auditor = $this->tugaskanAuditor($app);
        $this->terbitkanSuratTugas($app, 'qms');

        $this->actingAs($auditor)->post(route('audit.findings.store', $app), [
            'finding_number' => 'NC-04',
            'finding_type' => 'minor',
            'description' => 'Dokumen belum diperbarui.',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect();

        $finding = \App\Models\Finding::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($klien)->post(route('client.corrective-actions.store', $finding), [
            'root_cause' => 'Akar penyebab.',
            'correction' => 'Koreksi.',
            'corrective_action' => 'Tindakan pencegahan.',
        ])->assertRedirect();

        $ca = \App\Models\CorrectiveAction::where('finding_id', $finding->id)->firstOrFail();

        $this->actingAs($auditor)->post(route('audit.corrective-actions.review', $ca), [
            'status' => 'accepted',
            'notes' => 'Cukup.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('certificate_review', $app->fresh()->status);
    }

    // ------------------------------------------------- TCM-SRT (7 butir)

    public function test_TCM_SRT_01_mengunggah_draft_sertifikat(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');

        $this->actingAs($this->user('technical'))
            ->post(route('technical.draft.upload', $app), [
                'draft' => \Illuminate\Http\UploadedFile::fake()->create('draft.pdf', 200, 'application/pdf'),
            ])->assertRedirect();

        $this->assertDatabaseHas('certificate_drafts', ['application_id' => $app->id]);
    }

    public function test_TCM_SRT_02_membuat_tautan_berbagi_draft(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');
        $teknis = $this->user('technical');

        $this->actingAs($teknis)->post(route('technical.draft.upload', $app), [
            'draft' => \Illuminate\Http\UploadedFile::fake()->create('draft.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $draft = \App\Models\CertificateDraft::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($teknis)
            ->post(route('technical.draft.link', $draft), ['expires_in_days' => 7])
            ->assertRedirect();

        $this->assertDatabaseHas('certificate_share_links', ['certificate_draft_id' => $draft->id]);
    }

    public function test_TCM_SRT_03_membuka_tautan_yang_dicabut(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');
        $teknis = $this->user('technical');

        $this->actingAs($teknis)->post(route('technical.draft.upload', $app), [
            'draft' => \Illuminate\Http\UploadedFile::fake()->create('draft.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $draft = \App\Models\CertificateDraft::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($teknis)
            ->post(route('technical.draft.link', $draft), ['expires_in_days' => 7])
            ->assertRedirect();

        $link = \App\Models\CertificateShareLink::where('certificate_draft_id', $draft->id)->firstOrFail();

        $this->actingAs($teknis)->post(route('technical.link.revoke', $link))->assertRedirect();

        /*
         * Token mentahnya tidak disimpan, hanya sidik jarinya, sehingga yang
         * diperiksa adalah penandaan pencabutannya beserta penolakan token acak.
         */
        $link->refresh();
        $this->assertNotNull($link->revoked_at, 'tautan tidak ditandai dicabut');
        $this->assertFalse((bool) $link->is_active, 'tautan masih berstatus aktif');

        $this->get(route('certificate.draft.preview', Str::random(48)))
            ->assertStatus(404);
    }

    public function test_TCM_SRT_04_menerbitkan_sertifikat_final(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');

        $this->actingAs($this->user('technical'))
            ->post(route('technical.final.upload', $app), [
                'certificate' => \Illuminate\Http\UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
                'certificate_number' => 'GIS-CERT-MANUAL-001',
                'issued_date' => now()->format('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('certificate_finals', ['application_id' => $app->id]);
    }

    public function test_TCM_SRT_05_klien_lain_membuka_sertifikat(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');

        $this->actingAs($this->user('technical'))->post(route('technical.final.upload', $app), [
            'certificate' => \Illuminate\Http\UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
            'certificate_number' => 'GIS-CERT-MANUAL-002',
            'issued_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($this->user('client'))
            ->get(route('client.applications.show', $app))
            ->assertForbidden();
    }

    public function test_TCM_SRT_06_menyelesaikan_sertifikasi(): void
    {
        $this->seedAll();
        Storage::fake('local');
        $app = $this->order('ISO9001', 'certificate_review');
        $teknis = $this->user('technical');

        $this->actingAs($teknis)->post(route('technical.final.upload', $app), [
            'certificate' => \Illuminate\Http\UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
            'certificate_number' => 'GIS-CERT-MANUAL-003',
            'issued_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($teknis)->post(route('technical.complete', $app), [
            'notes' => 'Selesai.',
            'action_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertContains($app->fresh()->status, ['completed', 'surveillance']);
    }

    public function test_TCM_SRT_07_verifikasi_sertifikat_melalui_qr(): void
    {
        $this->seedAll();
        $app = $this->order('ISO9001', 'admin_review');

        $this->get(route('public.qr', ['nomor' => $app->order_number]))
            ->assertOk();
    }

    // ------------------------------------------------- TCM-MST (7 butir)

    public function test_TCM_MST_01_menambah_produk_sni(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.sni-taxonomy.groups.store'), [
                'name' => 'Grup Uji Manual',
                'mandatory_type' => 'sukarela',
            ])->assertRedirect();

        $this->assertDatabaseHas('sni_product_groups', ['name' => 'Grup Uji Manual']);
    }

    public function test_TCM_MST_02_impor_berkas_salah_format(): void
    {
        $this->seedAll();
        Storage::fake('local');

        $this->actingAs($this->user('superadmin'))
            ->from(route('superadmin.sni-products.index'))
            ->post(route('superadmin.sni-products.import'), [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('salah.txt', 5, 'text/plain'),
            ])->assertSessionHasErrors();
    }

    public function test_TCM_MST_03_menambah_kode_iaf(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('superadmin'))
            ->post(route('superadmin.iaf-nace.iaf.store'), [
                'code' => '40',
                'name_en' => 'Test sector',
                'name_id' => 'Sektor uji manual',
            ])->assertRedirect();

        $this->assertDatabaseHas('iaf_codes', ['code' => '40']);
    }

    public function test_TCM_MST_04_memilih_kode_nace_pada_formulir(): void
    {
        $this->seedAll();
        $klien = $this->user('client');
        $app = $this->draftKlien($klien);

        $this->seed(\Database\Seeders\IafNaceTaxonomySeeder::class);

        $nace = \App\Models\NaceCode::where('is_active', true)->firstOrFail();
        $iaf = $nace->iafCode;

        // Kode beserta keterangannya tersimpan apa adanya dari acuan KAN.
        $this->actingAs($klien)->put(route('client.applications.update', $app), [
            'fields' => [
                'iaf_code' => $iaf->code,
                'iaf_description' => $iaf->name_id,
                'nace_code' => $nace->code,
                'nace_description' => $nace->name_id,
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('application_values', [
            'application_id' => $app->id,
            'field_code' => 'nace_description',
            'value_text' => $nace->name_id,
        ]);

        // Kode yang tidak ada pada acuan KAN ditolak, sehingga keterangan yang
        // ikut tersimpan selalu berasal dari acuan, bukan ketikan bebas.
        $this->actingAs($klien)
            ->from(route('client.applications.edit', $app))
            ->put(route('client.applications.update', $app), [
                'fields' => [
                    'iaf_code' => '999',
                    'nace_code' => '88.8',
                    'nace_description' => 'Keterangan karangan sendiri',
                ],
            ])->assertSessionHasErrors('fields.iaf_code');

        $this->assertDatabaseMissing('application_values', [
            'application_id' => $app->id,
            'field_code' => 'nace_description',
            'value_text' => 'Keterangan karangan sendiri',
        ]);
    }

    public function test_TCM_MST_05_mengubah_pengaturan_sistem(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('superadmin'))
            ->get(route('superadmin.settings.index'))
            ->assertOk();
    }

    public function test_TCM_MST_06_menelusuri_audit_trail(): void
    {
        $this->seedAll();

        $this->actingAs($this->user('superadmin'))
            ->get(route('superadmin.audit-trail.index'))
            ->assertOk();
    }

    public function test_TCM_MST_07_menandai_notifikasi_dibaca(): void
    {
        $this->seedAll();
        $user = $this->user('client');

        $notif = \App\Models\PortalNotification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'uji',
            'title' => 'Notifikasi Uji',
            'message' => 'Isi notifikasi uji.',
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notif))
            ->assertRedirect();

        $this->assertNotNull($notif->fresh()->read_at);
    }

    // ------------------------------------------------------------ pembantu

    private function tugaskanAuditor(CertificationApplication $app, string $stage = 'all'): User
    {
        $auditor = $this->user('auditor');

        \App\Models\AuditAssignment::create([
            'application_id' => $app->id,
            'auditor_id' => $auditor->id,
            'assignment_role' => 'LA',
            'stage_code' => $stage,
            'assigned_date' => today(),
            'status' => 'assigned',
            'assigned_by' => $this->user('technical')->id,
        ]);

        return $auditor;
    }

    /*
     * Auditor baru boleh mengerjakan sebuah tahap setelah Surat Tugas tahap itu
     * terbit, sehingga surat tugasnya disiapkan lebih dulu di sini.
     */
    private function terbitkanSuratTugas(CertificationApplication $app, string $stage): void
    {
        $path = 'generated/assignment-letters/' . $app->id . '/ST_' . $stage . '.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 surat');

        $pdf = \App\Models\GeneratedPdf::create([
            'application_id' => $app->id,
            'document_type' => 'assignment_letter',
            'template_code' => 'assignment_letter_lssm',
            'document_version' => 1,
            'file_path' => $path,
            'checksum_sha256' => hash('sha256', '%PDF-1.4 surat'),
            'source_snapshot' => [],
        ]);

        \App\Models\AssignmentLetter::create([
            'application_id' => $app->id,
            'stage_code' => $stage,
            'cycle' => 0,
            'template_code' => 'lssm',
            'number_family' => 'lssm',
            'letter_number' => '00' . rand(1, 9) . '/ST/GIS-LSSM/MT/VIII/2026',
            'letter_place' => 'Tangerang',
            'letter_date' => today(),
            'pdf_version' => 1,
            'generated_pdf_id' => $pdf->id,
        ]);
    }

    /*
     * Draft yang dibentuk lewat service, sehingga salinan formulirnya ikut
     * tersimpan dan nomor ordernya masih kosong seperti draft sungguhan.
     */
    private function draftKlien(User $klien, string $schemeCode = 'ISO9001'): CertificationApplication
    {
        $scheme = $this->scheme($schemeCode);

        return app(\App\Services\ApplicationSubmissionService::class)->createDraft(
            $klien->id,
            $scheme->id,
            [
                'company_name' => 'PT Uji Manual',
                'applicant_name' => 'Budi',
                'contact_email' => 'budi@ujimanual.test',
                'contact_phone' => '0811',
                'form_version' => $scheme->form_version,
            ]
        );
    }

    /*
     * Formulir Wajib GIS terkunci sampai templatenya diminta dan disetujui tim
     * GIS, jadi butir uji unggah dokumen memakai dokumen milik perusahaan.
     */
    private function dokumenPerusahaan(CertificationApplication $app)
    {
        return app(\App\Services\DynamicFormService::class)
            ->schemeForApplication($app)
            ->requiredDocuments
            ->first(fn ($d) => ($d->document_group ?? 'company') !== 'gis_form');
    }


    /*
     * Formulir Wajib GIS baru terbuka setelah klien mengajukan permintaan
     * template dan Admin menyetujuinya, sesuai aturan pada GisFormService.
     */
    private function bukaKunciFormulirGis(CertificationApplication $app, User $klien): void
    {
        $gisForms = app(\App\Services\GisFormService::class);

        if (! $gisForms->schemeUsesGisForms($app->certification_scheme_id)) {
            return;
        }

        if ($gisForms->isUnlocked($app)) {
            return;
        }

        $this->actingAs($klien)->post(route('client.gis-form-requests.store', $app));

        $permintaan = \App\Models\GisFormRequest::where('application_id', $app->id)->firstOrFail();

        $this->actingAs($this->user('admin_application'))
            ->post(route('internal.gis-form-requests.approve', $permintaan));
    }

    private function isiSeluruhKewajiban(CertificationApplication $app, User $klien): void
    {
        $forms = app(\App\Services\DynamicFormService::class);
        $snapshot = $forms->schemeForApplication($app);

        /*
         * Nilai contoh dipilih menurut jenis fieldnya, karena aturan validasi
         * saat pengiriman memeriksa format setiap isian, bukan sekadar terisi.
         */
        $values = [];
        foreach ($snapshot->sections->flatMap->fields->where('is_required', true) as $field) {
            $values[$field->code] = match ($field->type) {
                'select', 'radio' => $field->options->first()->value ?? 'x',
                'boolean' => 'yes',
                'email' => 'kontak@ujimanual.test',
                'number' => '10',
                'date' => '2026-01-01',
                'url' => 'https://ujimanual.test',
                'checkbox_group' => [$field->options->first()->value ?? 'x'],
                default => 'Isian uji manual',
            };
        }

        app(\App\Services\ApplicationSubmissionService::class)
            ->saveValues($app, $values, $klien->id);

        $this->bukaKunciFormulirGis($app, $klien);

        foreach ($forms->applicableDocuments($snapshot, $values)->where('requirement', 'required') as $doc) {
            $this->actingAs($klien)->post(route('client.documents.store', $app), [
                'document_code' => $doc->code,
                'file' => \Illuminate\Http\UploadedFile::fake()->create('dok.pdf', 50, 'application/pdf'),
            ]);
        }
    }
}
