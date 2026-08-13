<?php

/*
 * Surat Tugas auditor.
 *
 * Seluruh teks surat hidup di sini, bukan di dalam service penggambar PDF,
 * supaya redaksinya bisa disesuaikan tanpa menyentuh kode tata letak. Struktur
 * ketiganya sama: kop, judul, tempat+tanggal, nomor, alamat tujuan, kalimat
 * pembuka, blok "label : nilai", tabel auditor, kalimat tujuan, blok kedua,
 * lalu blok tanda tangan yang sama untuk ketiganya.
 *
 * Tiga keluarga mengikuti review_template pada skema:
 *   sni          -> lspro  (bahasa Inggris, "ASSIGNMENT LETTER")
 *   ispo         -> ispo   (kalimat tujuan bergantung tahap audit)
 *   lssm & lsml  -> lssm   (seluruh skema ISO)
 */

return [

    /*
     * Peta review_template skema -> keluarga surat.
     */
    'families' => [
        'sni' => 'lspro',
        'ispo' => 'ispo',
        'lssm' => 'lssm',
        'lsml' => 'lssm',
    ],

    'default_family' => 'lssm',

    /*
     * Peran pada audit_assignments -> tulisan kolom "Posisi" di tabel auditor.
     * Tetap bisa ditimpa per baris (contoh nyata: "Lead Auditor/PPC").
     */
    'role_labels' => [
        'LA' => 'Lead Auditor',
        'A' => 'Auditor',
        'TA' => 'Tenaga Ahli',
    ],

    'role_order' => ['LA', 'A', 'TA'],

    'default_place' => 'Tangerang',

    /*
     * Kop surat. Warnanya diambil dari berkas .docx asli: nama lembaga biru
     * tua (1F477B) dan tagline merah (FF0000). Ketiga template memakai palet
     * yang sama.
     */
    'letterhead' => [
        'company' => 'PT. GLOBAL INSPEKSI SERTIFIKASI',
        'company_color' => '1F477B',
        'tagline' => 'Inspection & Certification Body',
        'tagline_color' => 'FF0000',
        'rule_color' => '1F477B',
    ],

    'templates' => [

        'lspro' => [
            'title' => 'ASSIGNMENT LETTER',
            'number_pattern' => '{SEQ}/LSPr-ST/GIS/MT/{ROMAN_MONTH}/{YYYY}',
            'number_label' => 'No.',
            'recipient_heading' => 'To Director :',
            'salutation' => 'With Regards,',
            'intro' => 'Referring to the application letter for certification of :',
            'assign_line' => 'LSPro PT. Global Inspeksi Sertifikasi assign :',
            'purpose' => 'To inspection of :',
            'table_headings' => ['No.', 'Nama', 'Posisi'],
            'primary_fields' => [
                'company_name' => 'Company name',
                'company_address' => 'Company Address',
                'phone_fax' => 'No. telp & fax',
                'order_number' => 'Order number',
            ],
            'detail_fields' => [
                'commodity' => 'Commodity',
                'type_brands' => 'Type/Brands',
                'sni_number' => 'Number of SNI',
                'location' => 'Location',
                'assignment_date' => 'Assignment date',
                'laboratory' => 'Laboratory',
                'laboratory_address' => 'Laboratory Address',
            ],
            // Isian yang tidak punya sumber di form klien — wajib diketik Teknis.
            'manual_fields' => ['laboratory', 'laboratory_address'],
            'signature_block' => [
                'Products Certification Body',
                'PT. Global Inspeksi Sertifikasi',
            ],
            'default_signer_position' => 'Administration Manager',
        ],

        'lssm' => [
            'title' => 'SURAT TUGAS',
            'number_pattern' => '{SEQ}/ST/GIS-LSSM/MT/{ROMAN_MONTH}/{YYYY}',
            'number_label' => 'No.',
            'recipient_heading' => 'Kepada Yth Direktur',
            'salutation' => 'Dengan hormat,',
            'intro' => 'Sehubungan dengan adanya kegiatan Sistem Manajemen dari :',
            'assign_line' => 'dengan ini PT. Global Inspeksi Sertifikasi menugaskan :',
            'purpose' => 'untuk melakukan kegiatan audit pada :',
            'table_headings' => ['No.', 'Nama', 'Posisi'],
            'primary_fields' => [
                'company_name' => 'Nama Klien',
                'company_address' => 'Alamat Klien',
                'phone_fax' => 'No. telp & fax',
                'order_number' => 'Order number',
            ],
            'detail_fields' => [
                'company_name' => 'Nama Perusahaan',
                'industry_scope' => 'Lingkup Industri',
                'specific_scope' => 'Lingkup Spesifik',
                'location' => 'Lokasi',
                'standard' => 'Standard',
                'assignment_date' => 'Tanggal Penugasan',
            ],
            'manual_fields' => [],
            'signature_block' => [
                'Products Certification Body',
                'PT. Global Inspeksi Sertifikasi',
            ],
            'default_signer_position' => 'Administration Manager',
        ],

        'ispo' => [
            'title' => 'SURAT TUGAS',
            'number_pattern' => '{SEQ}/ISPO-ST/GIS/MT/{ROMAN_MONTH}/{YYYY}',
            'number_label' => 'Nomor :',
            'recipient_heading' => 'Kepada Yth',
            'recipient_subheading' => 'Direktur',
            'salutation' => 'Dengan hormat,',
            'intro' => 'Sehubungan dengan adanya kegiatan sertifikasi ISPO dari :',
            'assign_line' => 'dengan ini PT Global Inspeksi Sertifikasi menugaskan :',
            // Kalimat tujuan ISPO menyebut tahap auditnya — lihat purpose_by_stage.
            'purpose' => 'untuk melakukan kegiatan audit :stage pada :',
            'purpose_by_stage' => [
                'stage_1' => 'Tahap Pertama',
                'stage_2' => 'Tahap Kedua',
                'qms' => 'Lapangan/QMS',
            ],
            'table_headings' => ['No', 'Nama', 'Posisi'],
            'primary_fields' => [
                'company_name' => 'Nama Klien',
                'company_address' => 'Alamat Klien',
                'phone_fax' => 'No. telp & faks',
                'order_number' => 'Nomer order/Tinjauan',
            ],
            'detail_fields' => [
                'company_name' => 'Nama Perusahaan',
                'scope' => 'Lingkup',
                'location' => 'Lokasi',
                'assignment_date' => 'Tanggal Penugasan',
            ],
            'manual_fields' => ['scope', 'location'],
            'signature_block' => [
                'Products Certification Body',
                'PT. Global Inspeksi Sertifikasi',
            ],
            'default_signer_position' => 'Administration Manager',
        ],
    ],

    /*
     * Kalimat tujuan per tahap untuk keluarga selain ISPO. Dipakai sebagai
     * pelengkap judul kartu di UI, bukan mengubah kalimat pada surat.
     */
    'stage_labels' => [
        'stage_1' => 'Audit Tahap 1',
        'stage_2' => 'Audit Tahap 2',
        'qms' => 'Audit Lapangan / QMS',
    ],

    'number_padding' => 3,

    // Unggahan tanda tangan berstempel: hanya gambar, karena SimplePdf
    // menggambar JPEG dan DrawsSignatures mengonversi PNG lebih dahulu.
    'signature_extensions' => ['jpg', 'jpeg', 'png'],
    'signature_max_mb' => 5,
];
