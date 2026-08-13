<?php

namespace Database\Seeders;

use App\Models\IafCode;
use App\Models\NaceCode;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Ruang lingkup akreditasi KAN K-07.01 Rev.2 Lampiran 1.
 *
 * Datanya di berkas JSON terpisah, mengikuti pola SchemeCatalogSeeder: daftar
 * sepanjang ini tidak enak dibaca sebagai konstanta di dalam kelas.
 */
class IafNaceTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $berkas = database_path('seeders/data/iaf-nace.json');

        if (! is_file($berkas)) {
            throw new RuntimeException('Berkas acuan IAF/NACE tidak ditemukan: '.$berkas);
        }

        $daftar = json_decode(file_get_contents($berkas), true);

        if (! is_array($daftar) || $daftar === []) {
            throw new RuntimeException('Berkas acuan IAF/NACE kosong atau tidak valid.');
        }

        foreach ($daftar as $urutanIaf => $iaf) {
            /*
             * is_active hanya diisi saat baris pertama kali dibuat. Menjalankan
             * ulang seeder tidak boleh menghidupkan kembali kode yang sengaja
             * dinonaktifkan Superadmin.
             */
            $barisIaf = IafCode::firstOrNew(['code' => $iaf['code']]);
            $barisIaf->fill([
                'name_en' => $iaf['name_en'],
                'name_id' => $iaf['name_id'],
                'sort_order' => $urutanIaf + 1,
            ]);
            if (! $barisIaf->exists) {
                $barisIaf->is_active = true;
            }
            $barisIaf->save();

            foreach ($iaf['nace'] as $urutanNace => $nace) {
                $barisNace = NaceCode::firstOrNew([
                    'iaf_code_id' => $barisIaf->id,
                    'code' => $nace['code'],
                ]);
                $barisNace->fill([
                    'name_en' => $nace['name_en'],
                    'name_id' => $nace['name_id'],
                    'sort_order' => $urutanNace + 1,
                ]);
                if (! $barisNace->exists) {
                    $barisNace->is_active = true;
                }
                $barisNace->save();
            }
        }
    }
}
