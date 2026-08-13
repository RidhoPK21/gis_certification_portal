<?php

namespace Tests\Feature;

use App\Models\IafCode;
use App\Models\NaceCode;
use App\Models\SniProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data acuan harus ikut terpasang saat instalasi.
 *
 * gis:install menjalankan daftar seeder yang ditulis eksplisit, bukan
 * DatabaseSeeder, sehingga seeder baru yang lupa didaftarkan di sana akan
 * menghasilkan portal dengan dropdown kosong di server — dan tidak ada test
 * lain yang menangkapnya.
 */
class InstallSeedsReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_instalasi_memuat_acuan_iaf_nace_dan_taksonomi_sni(): void
    {
        $this->assertSame(0, IafCode::count(), 'Database uji seharusnya mulai kosong.');

        /*
         * gis:install meminta email superadmin secara interaktif bila .env
         * belum mengisinya; disediakan di sini agar perintahnya berjalan tanpa
         * prompt, sebagaimana di server yang .env-nya sudah lengkap.
         */
        putenv('GIS_ADMIN_EMAIL=superadmin@uji.test');
        $_ENV['GIS_ADMIN_EMAIL'] = 'superadmin@uji.test';
        putenv('GIS_ADMIN_PASSWORD=RahasiaKuatUji123');
        $_ENV['GIS_ADMIN_PASSWORD'] = 'RahasiaKuatUji123';

        $this->artisan('gis:install', ['--force' => true])->assertExitCode(0);

        $this->assertGreaterThan(0, IafCode::count(), 'Kode IAF tidak ikut terpasang saat instalasi.');
        $this->assertGreaterThan(0, NaceCode::count(), 'Kode NACE tidak ikut terpasang saat instalasi.');
        $this->assertGreaterThan(0, SniProductGroup::count(), 'Taksonomi produk SNI tidak ikut terpasang.');

        // Contoh yang dipakai sebagai acuan perilaku dropdown.
        $iaf = IafCode::with('naceCodes')->where('code', '16')->first();
        $this->assertNotNull($iaf, 'IAF 16 tidak ditemukan setelah instalasi.');
        $this->assertSame(['23.5', '23.6'], $iaf->naceCodes->pluck('code')->all());
    }
}
