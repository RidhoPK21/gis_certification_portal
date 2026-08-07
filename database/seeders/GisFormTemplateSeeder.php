<?php

namespace Database\Seeders;

use App\Models\CertificationScheme;
use App\Models\GisFormTemplate;
use App\Models\SchemeRequiredDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Menanam template Formulir Wajib GIS bawaan agar fitur langsung terpakai
 * setelah deploy. Superadmin tetap dapat menggantinya lewat menu Formulir GIS;
 * seeder hanya mengisi versi awal dan tidak menimpa berkas yang sudah diunggah.
 */
class GisFormTemplateSeeder extends Seeder
{
    /**
     * Template per skema; kode template dipetakan ke item checklist yang harus
     * diisi klien. Tiap skema memakai berkas terbitan GIS masing-masing.
     */
    private const TEMPLATES = [
        'ISO9001' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-Sistem-Manajemen.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Berisi data perusahaan, jumlah karyawan, dan lokasi yang diajukan untuk sertifikasi.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-Permohonan-Sertifikasi.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi.docx',
                'sort_order' => 3,
            ],
        ],
        'ISO27001' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-Keamanan-Informasi.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte, izin usaha, struktur organisasi, dan dokumen sistem keamanan informasi.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSMKI.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-Keamanan-Informasi.docx',
                'sort_order' => 3,
            ],
        ],
        'ISO22000' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-Pangan.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte, izin usaha, struktur organisasi, dan dokumen sistem keamanan pangan.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSMKP.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-Pangan.docx',
                'sort_order' => 3,
            ],
        ],
        'ISO20000' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-LTI.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte notaris, izin usaha, TDP, struktur organisasi, dan dokumen sistem lainnya.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSMLTI.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-LTI.docx',
                'sort_order' => 3,
            ],
        ],
        'ISO14001' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-Lingkungan.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen Lingkungan',
                'description' => 'Versi LSSML: memuat pilihan sistem manajemen ISO 9001 dan/atau ISO 14001.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSML.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'Fr.7202',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'Fr.7202-Perjanjian-Kerjasama-Sertifikasi.doc',
                'sort_order' => 3,
            ],
        ],
        'ISO45001' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SMK3.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte, izin usaha, struktur organisasi, dan dokumen sistem manajemen K3.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSMK3.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-SMK3.docx',
                'sort_order' => 3,
            ],
        ],
        'HACCP' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-HACCP.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte, NIB, struktur organisasi, dan dokumen sistem HACCP.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSHACCP.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-HACCP.docx',
                'sort_order' => 3,
            ],
        ],
        'ISO37001' => [
            [
                'code' => 'FrM.9100',
                'name' => 'Surat Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dicetak pada kop surat perusahaan dan ditandatangani Direktur Utama.',
                'document_code' => 'application_letter',
                'file' => 'FrM.9100-Surat-Permohonan-Sertifikasi-SM-Anti-Penyuapan.doc',
                'sort_order' => 1,
            ],
            [
                'code' => 'FrM.9104',
                'name' => 'Form Aplikasi Permohonan Sertifikasi Sistem Manajemen',
                'description' => 'Dilampirkan bersama akte, NIB, struktur organisasi, dan dokumen sistem manajemen anti penyuapan.',
                'document_code' => 'application_form_signed',
                'file' => 'FrM.9104-Form-Aplikasi-LSSMAP.docx',
                'sort_order' => 2,
            ],
            [
                'code' => 'FrM.9102',
                'name' => 'Surat Perjanjian Kerjasama Sertifikasi',
                'description' => 'Wajib bermaterai dan ditandatangani kedua belah pihak.',
                'document_code' => 'cooperation_agreement',
                'file' => 'FrM.9102-Perjanjian-Kerjasama-Sertifikasi-Anti-Penyuapan.docx',
                'sort_order' => 3,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $schemeCode => $templates) {
            $this->seedScheme($schemeCode, $templates);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function seedScheme(string $schemeCode, array $templates): void
    {
        $scheme = CertificationScheme::where('code', $schemeCode)->first();

        if (! $scheme) {
            $this->command?->warn('Skema '.$schemeCode.' belum ada; seeder template dilewati.');

            return;
        }

        $disk = Storage::disk('private');

        foreach ($templates as $item) {
            $source = database_path('seeders/data/gis-forms/' . $item['file']);

            if (! is_file($source)) {
                $this->command?->warn('Berkas template ' . $item['file'] . ' tidak ditemukan.');

                continue;
            }

            $existing = GisFormTemplate::where('certification_scheme_id', $scheme->id)
                ->where('code', $item['code'])
                ->first();

            // Template yang sudah diganti superadmin tidak boleh ditimpa seeder.
            if ($existing && $existing->version > 1) {
                continue;
            }

            $extension = pathinfo($item['file'], PATHINFO_EXTENSION);
            $path = 'gis-form-templates/' . $scheme->id . '/seed_' . strtolower(str_replace('.', '', $item['code'])) . '.' . $extension;

            $disk->put($path, file_get_contents($source));

            GisFormTemplate::updateOrCreate(
                ['certification_scheme_id' => $scheme->id, 'code' => $item['code']],
                [
                    'scheme_required_document_id' => SchemeRequiredDocument::where('certification_scheme_id', $scheme->id)
                        ->where('code', $item['document_code'])
                        ->value('id'),
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'version' => 1,
                    'original_name' => $item['file'],
                    'stored_name' => basename($path),
                    'file_path' => $path,
                    'mime_type' => $extension === 'doc'
                        ? 'application/msword'
                        : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'extension' => $extension,
                    'size_bytes' => (int) filesize($source),
                    'checksum_sha256' => hash_file('sha256', $source),
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
