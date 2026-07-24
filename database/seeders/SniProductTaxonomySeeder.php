<?php

namespace Database\Seeders;

use App\Models\SniProductCategory;
use App\Models\SniProductGroup;
use Illuminate\Database\Seeder;

class SniProductTaxonomySeeder extends Seeder
{
    /**
     * Taksonomi produk SNI (LSPro) dari halaman layanan giscert.
     * Grup => [mandatory_type, [kategori...]]
     */
    private const TAXONOMY = [
        ['Kelistrikan, Elektronika & Luminer', 'wajib', [
            'Kipas Angin', 'Lampu Swabalast untuk pelayanan pencahayaan umum', 'Lampu LED swabalast dengan tegangan > 50 V',
            'Lampu LED Swaballast (SKEM)', 'Lampu LED Tabung Swaballast (SKEM)', 'Fitting lampu arus bolak-balik',
            'Fitting lampu untuk lampu LED linear berkaki dobel', 'Luminer magun kegunaan umum', 'Luminer tanam',
            'Luminer untuk pencahayaan jalan umum', 'Luminer kegunaan umum portable', 'Luminer Lampu sorot',
            'Luminer rantai cahaya / Cahaya Tali', 'Luminer lampu tidur', 'Lumier untuk pencahayaan darurat',
            'Panel Hubung Bagi (PHB), Kotak, Selungkup', 'Sistem Konduit - Konduit & Gawai Pemagun', 'Saklar',
            'Tusuk kontak dan kotak kontak', 'Pemutus sirkuit (MCB)', 'Perlengkapan kendali lampu',
            'Pemutus sirkit arus sisa (RCBO) tergantung & tak tergantung voltase',
            'Rakitan perangkat sekelar dan kendali voltase rendah', 'Adaptor',
        ]],
        ['Minyak Lumas & Migas', 'wajib', [
            'Minyak goreng sawit', 'Minyak lumas motor bensin 4 langkah kendaraan bermotor',
            'Minyak lumas motor bensin 4 langkah sepeda motor', 'Minyak lumas motor bensin 2 langkah (pendingin udara)',
            'Minyak lumas motor bensin 2 langkah (pendingin air)', 'Minyak lumas motor diesel putaran tinggi',
            'Minyak lumas roda gigi transmisi manual & gardan', 'Minyak lumas transmisi otomatis',
        ]],
        ['Pupuk Dasar', 'wajib', [
            'Pupuk SP-36', 'Pupuk fosfat alam', 'Pupuk urea', 'Pupuk triple superfosfat (TSP)',
            'Pupuk amonium sulfat', 'Pupuk NPK padat', 'Pupuk kalium klorida', 'Pupuk organik padat',
        ]],
        ['Baja, Logam & Konstruksi', 'wajib', [
            'Kawat baja kuens (quench) temper', 'Kawat baja tanpa lapisan', 'Tali kawat baja',
            'Tujuh kawat baja tanpa lapisan dipilin', 'Baja lembaran lapis seng', 'Baja tulangan beton hasil canai panas ulang',
            'Baja tulangan beton dalam bentuk gulungan', 'Baja tulangan beton', 'Peralatan masak (cookware) dari logam',
            'Peralatan makan & perlengkapan masak baja tahan karat (Flatware)', 'Pipa baja stainless',
        ]],
        ['Kebutuhan Lainnya (Wajib)', 'wajib', [
            'Mainan', 'Sepeda anak', 'Air mineral', 'Minyak makan merah', 'Minyak kelapa sawit CPO',
        ]],
        ['Peralatan Kesehatan (Alkes)', 'sukarela', [
            'Inkubator Infant', 'Tempat tidur pasien', 'Meja Operasi', 'Kursi roda',
        ]],
        ['Alat & Mesin Pertanian', 'sukarela', [
            'Mesin pemipil jagung', 'Mesin Perontok Padi tipe pelemparan Jerami',
            'Mesin perontok multikomoditi (padi, jagung, kedelai)', 'Pompa Irigasi', 'Traktor pertanian roda dua',
            'Sprayer gendong elektrik', 'Sprayer gendong semi otomatis', 'Traktor pertanian roda empat', 'Pakan sapi potong',
        ]],
        ['Pelumas Industri Khusus', 'sukarela', [
            'Minyak Lumas Sirkulasi', 'Minyak lumas hidrolik industri jenis anti aus', 'Minyak lumas roda gigi industri tertutup',
            'Minyak lumas motor diesel putaran menengah (industri/kapal)', 'Minyak lumas motor diesel putaran rendah',
            'Minyak lumas motor gas stasioner', 'Minyak lumas turbin',
        ]],
        ['Pupuk Spesifik & Perkebunan', 'sukarela', [
            'Pupuk tripel super fosfat plus Zn', 'Pupuk kiserit', 'Pupuk borat', 'Pupuk super fosfat tunggal',
            'Pupuk dolomit', 'Kapur untuk pertanian', 'Pupuk amonium klorida', 'Pupuk monoamonium fosfat',
            'Pupuk diamonium fosfat',
        ]],
        ['Bahan Bangunan Sipil', 'sukarela', [
            'Kawat baja karbon rendah', 'Baja lembaran alumunium lapis seng', 'Profil rangka baja ringan',
            'Aluminium foil', 'Paku baja', 'Beton struktural (Spesifikasi)', 'Bata beton (paving block)',
            'Bata Ringan Untuk Pasangan Dinding', 'Bata Beton Untuk Pasangan Dinding', 'Genteng beton',
            'Spesifikasi turap beton prategang bergelombang', 'Atap dan dinding gelombang baja', 'Ready mix',
            'Beton Aerasi Autoklaf', 'Jaring Kawat Baja Las Untuk Tulangan Beton', 'Tiang pancang',
        ]],
        ['Kebutuhan Umum & Gas', 'sukarela', [
            'Kompor gas dua/tiga tungku', 'Kompor gas satu tungku',
        ]],
        ['Usaha Pariwisata, F&B & Jasa', 'sukarela', [
            'Usaha Pariwisata', 'Bumi perkemahan', 'Restoran bintang', 'Persinggahan Karavan', 'Motel', 'Hotel',
            'Hotel bintang', 'Kondominium hotel / Apartemen servis', 'Bar/Pub', 'Rumah wisata', 'Restoran / Rumah Makan',
            'Pengelolaan Pariwisata Alam', 'Ruang Bermain Ramah Anak (Child Friendly Playground)', 'Pasar rakyat', 'CHSE',
            'Wisata Selam', 'Jasa Boga', 'Kawasan Pariwisata', 'Jasa Manajemen hotel / hunian wisata senior',
            'Pusat Penjualan Makanan', 'Wisata Arung Jeram', 'Kafe', 'Kelab malam', 'Penyelenggara layanan rehabilitasi NAPZA',
        ]],
    ];

    public function run(): void
    {
        foreach (self::TAXONOMY as $groupOrder => [$groupName, $mandatory, $categories]) {
            $group = SniProductGroup::updateOrCreate(
                ['name' => $groupName],
                ['mandatory_type' => $mandatory, 'sort_order' => $groupOrder + 1, 'is_active' => true]
            );

            foreach ($categories as $categoryOrder => $categoryName) {
                SniProductCategory::updateOrCreate(
                    ['sni_product_group_id' => $group->id, 'name' => $categoryName],
                    ['sort_order' => $categoryOrder + 1, 'is_active' => true]
                );
            }
        }
    }
}
