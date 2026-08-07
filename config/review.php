<?php

/*
 * Baris tabel tinjauan FrM.9108/GIS (SMAP). Formulir ini memakai daftar yang
 * sama persis pada bagian administrasi maupun teknis, jadi didefinisikan sekali
 * di sini lalu dipakai dua kali pada 'form_rows' di bawah — kalau daftarnya
 * diketik ulang, keduanya cepat atau lambat akan berbeda tanpa disadari.
 *
 * Lima baris terakhir bukan dokumen unggahan klien melainkan penilaian
 * peninjau, jadi ditandai 'document' => false dengan keterangan teks bebas.
 * Checklist klien sendiri tetap 14 dokumen perusahaan + 3 Formulir Wajib GIS.
 */
$iso37001Rows = [
    ['code' => 'business_license_review', 'label' => 'SIUP (Surat Ijin Usaha / Industri)', 'document' => false, 'remark' => 'text'],
    ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
    ['code' => 'nib', 'label' => 'TDP (Tanda Daftar Perusahaan) / NIB Berisiko'],
    ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi'],
    ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi'],
    ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
    ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
    ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
    ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit'],
    ['code' => 'risk_management_file', 'label' => 'Dokumen Management Resiko'],
    ['code' => 'process_information', 'label' => 'Proses bisnis yang dilakukan'],
    ['code' => 'due_diligence', 'label' => 'Dokumen uji kelayakan'],
    ['code' => 'integrity_pact', 'label' => 'Fakta Integritas'],
    ['code' => 'anti_bribery_policy', 'label' => 'Kebijakan Anti Penyuapan'],
    ['code' => 'abms_document_review', 'label' => 'Dokumen Anti Penyuapan Mutu 37001', 'document' => false, 'remark' => 'text'],
    ['code' => 'litigation_process', 'label' => 'Proses Litigasi (Penjelasan Secara Hukum)', 'document' => false, 'remark' => 'text'],
    ['code' => 'litigation', 'label' => 'Litigasi', 'document' => false, 'remark' => 'text'],
    ['code' => 'bribery_risk_assessment', 'label' => 'Analisa Resiko dalam Penerapan Anti Suap'],
    ['code' => 'anti_bribery_system', 'label' => 'Sistem penerapan Anti Bribery', 'document' => false, 'remark' => 'text'],
];

/*
 * Baris tabel tinjauan FrM.9114/GIS. Formulir cetaknya dipakai bersama oleh
 * sistem manajemen keamanan pangan dan HACCP — kolom Standar Audit pada berkas
 * aslinya berbunyi "SNI ISO 22000:2018 / SNI CXC 1:1969" — jadi didefinisikan
 * sekali di sini lalu dipakai kedua skema pada 'form_rows' di bawah.
 */
$lssmkpRows = [
    'administration' => [
        ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
        ['code' => 'nib', 'label' => 'NIB'],
        ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SM'],
        ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
        ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
        ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
        ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
    ],
    'technical' => [
        ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
        ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
        ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
        ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
        ['code' => 'risk_management_file', 'label' => 'Hasil identifikasi dan penilaian risiko'],
        ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
        ['code' => 'prp_document', 'label' => 'Dokumen Pengendalian SMKP, HACCP / Dokumen Program Prasyarat Dasar / Analisis bahaya dan Titik kendali kritis'],
    ],
];

return [
    /*
     * Aspek yang dinilai pada Tinjauan Teknis permohonan.
     * Dipakai bersama oleh form tinjauan teknis (halaman Tim Teknis)
     * dan ringkasan read-only pada halaman Admin, agar tidak divergen.
     *
     * key   = kode nilai permohonan (application_values.field_code)
     * label = teks yang ditampilkan pada form dan PDF
     * input = jenis kolom isian pada form Tim Teknis
     *
     * Kode-kode ini sengaja TIDAK terdaftar sebagai scheme_fields: nilainya
     * bukan isian klien melainkan hasil kajian Tim Teknis, dan disimpan lewat
     * ReviewService::storeTechnicalAspects().
     */
    'technical_fields' => [
        'audit_mandays' => [
            'label' => 'Jumlah mandays audit (stage 1 + stage 2)',
            'input' => 'number',
        ],
        'required_auditor_competence' => [
            'label' => 'Kompetensi auditor yang diperlukan',
            'input' => 'textarea',
        ],
    ],

    /*
     * Peran akun yang boleh dipilih sebagai panelis. Tim auditor tidak diatur
     * di sini karena diambil dari penugasan auditor (audit_assignments) beserta
     * peran LA/A/TA-nya.
     */
    'panelist_roles' => ['technical', 'superadmin'],

    /*
     * Kolom "Kompetensi spesifik auditor untuk SNI ISO 14001:2015 yang
     * diperlukan" pada FrM.9101: tujuh aspek lingkungan yang dicentang Tim
     * Teknis. Kodenya mengikuti penomoran klausul pada formulir.
     */
    'environmental_competences' => [
        '6.1' => 'Emisi ke udara',
        '6.2' => 'Pembuangan ke tanah',
        '6.3' => 'Pembuangan ke air',
        '6.4' => 'Penggunaan bahan baku, energi dan sumber daya alam',
        '6.5' => 'Energi yang dipancarkan (panas, cahaya, radiasi ion, getaran, kebisingan)',
        '6.6' => 'Limbah',
        '6.7' => 'Atribut fisik',
    ],

    /*
     * ====== Struktur formulir FrM.9107/GIS (Tinjauan Permohonan SMM) ======
     *
     * Formulir aslinya memakai konvensi "*) coret yang tidak perlu": seluruh
     * pilihan dicetak berjajar dan yang tidak dipilih dicoret. Karena itu tiap
     * pilihan disimpan sebagai kode, bukan teks bebas — nilai kodenya dipakai
     * form (dropdown) dan PDF (menentukan mana yang dicoret) sekaligus.
     */

    /*
     * Baris tabel tinjauan persis seperti formulir cetaknya.
     *
     * Daftar ini sengaja terpisah dari checklist dokumen klien: formulir GIS
     * hanya mengkaji sebagian dokumen (FrM.9107 = 7 baris administrasi + 7 baris
     * teknis, FrM.9101 = 11 + 15), sedangkan checklist klien memuat seluruh
     * kelengkapan yang harus diunggah. Kode baris mengacu ke kode dokumen
     * checklist supaya berkas unggahannya bisa langsung dibuka peninjau.
     *
     * Kuncinya kode skema, bukan template: tujuh skema memakai template 'lssm'
     * tetapi daftar dokumennya berbeda-beda. Skema yang belum terdaftar di sini
     * tetap memakai cara lama, yaitu seluruh dokumen checklist dikelompokkan
     * lewat scheme_required_documents.review_group.
     */
    'form_rows' => [
        // FrM.9107/GIS — sistem manajemen mutu.
        'ISO9001' => [
            'administration' => [
                ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
                ['code' => 'nib', 'label' => 'NIB'],
                ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SM'],
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
            ],
            'technical' => [
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'risk_management_file', 'label' => 'Risk management file'],
                ['code' => 'process_information', 'label' => 'Proses produksi / bisnis pada perusahaan beserta informasi terkait'],
                ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
            ],
        ],

        // FrM.9112/GIS — sistem manajemen layanan teknologi informasi.
        'ISO20000' => [
            'administration' => [
                ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
                ['code' => 'nib', 'label' => 'NIB'],
                ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SM'],
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
            ],
            'technical' => [
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'risk_management_file', 'label' => 'Hasil identifikasi dan penilaian risiko'],
                ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
                ['code' => 'sla_document', 'label' => 'Dokumen Service Level Agreement (SLA)'],
                ['code' => 'service_catalog', 'label' => 'Dokumen Katalog Layanan'],
            ],
        ],

        /*
         * FrM.9101/GIS-7 — sistem manajemen keamanan informasi.
         *
         * Enam baris terakhir tabel administrasi adalah penilaian peninjau,
         * bukan dokumen unggahan klien: ditandai 'document' => false dengan
         * keterangan berupa teks bebas.
         */
        'ISO27001' => [
            'administration' => [
                ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
                ['code' => 'nib', 'label' => 'NIB'],
                ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SM'],
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'business_type_regulation', 'label' => 'Penilaian Bisnis dan Kompleksitas Perusahaan: tipe bisnis dan persyaratan peraturan', 'document' => false, 'remark' => 'text'],
                ['code' => 'business_process_task', 'label' => 'Penilaian Bisnis dan Kompleksitas Perusahaan: proses dan tugas', 'document' => false, 'remark' => 'text'],
                ['code' => 'business_ms_level', 'label' => 'Penilaian Bisnis dan Kompleksitas Perusahaan: level MS', 'document' => false, 'remark' => 'text'],
                ['code' => 'it_infrastructure', 'label' => 'Penilaian Lingkup IT Perusahaan: infrastruktur IT dan kompleksitas', 'document' => false, 'remark' => 'text'],
                ['code' => 'it_outsourcing', 'label' => 'Penilaian Lingkup IT Perusahaan: ketergantungan terhadap outsourcing/supplier', 'document' => false, 'remark' => 'text'],
                ['code' => 'it_system_development', 'label' => 'Penilaian Lingkup IT Perusahaan: pengembangan sistem informasi', 'document' => false, 'remark' => 'text'],
            ],
            'technical' => [
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'risk_management_file', 'label' => 'Hasil identifikasi dan penilaian risiko keamanan informasi'],
                ['code' => 'soa', 'label' => 'Statement of Applicability (SoA) terkait hasil penilaian risiko keamanan informasi dan rencana penanganan risiko keamanan informasi'],
                ['code' => 'risk_methodology', 'label' => 'Prosedur yang berisi pengaturan tentang cara menentukan dan menilai tingkat risiko keamanan informasi'],
                ['code' => 'information_security_policy', 'label' => 'Kebijakan keamanan informasi'],
                ['code' => 'security_objectives', 'label' => 'Sasaran keamanan informasi'],
                ['code' => 'internal_audit_evidence', 'label' => 'Hasil audit internal sistem manajemen keamanan informasi'],
                ['code' => 'management_review_evidence', 'label' => 'Hasil Tinjauan Manajemen Puncak terhadap kinerja sistem manajemen keamanan informasi'],
                ['code' => 'data_center_document', 'label' => 'Dokumen yang menunjukkan / mengatur lokasi data center dan disaster recovery site (jika ada)'],
            ],
        ],

        /*
         * FrM.9114/GIS — sistem manajemen keamanan pangan.
         *
         * Formulir cetaknya satu berkas untuk dua skema: kolom Standar Audit
         * berbunyi "SNI ISO 22000:2018 / SNI CXC 1:1969". Karena itu keduanya
         * memakai $lssmkpRows yang sama — kalau daftarnya disalin, cepat atau
         * lambat salah satunya akan tertinggal saat formulirnya direvisi.
         */
        'ISO22000' => $lssmkpRows,
        'HACCP' => $lssmkpRows,

        /*
         * FrM.9108/GIS — sistem manajemen anti penyuapan (SMAP).
         *
         * Kedua tahap memakai daftar 19 baris yang sama ($iso37001Rows di atas):
         * Admin memeriksa kelengkapannya, Tim Teknis memeriksa substansinya, dan
         * hasilnya berdiri sendiri karena tersimpan pada review_form_items
         * masing-masing tahap.
         */
        'ISO37001' => [
            'administration' => $iso37001Rows,
            'technical' => $iso37001Rows,
        ],

        /*
         * FrM.9111/GIS-0 — sistem manajemen keselamatan dan kesehatan kerja.
         *
         * Empat baris terakhir tabel teknis pada formulir cetaknya masih
         * berbunyi "lingkungan" — tampaknya sisa salin dari formulir LSSML.
         * Teksnya dipertahankan apa adanya sesuai formulir, tetapi karena
         * dokumen-dokumen itu tidak ada pada checklist SMK3, barisnya menjadi
         * penilaian peninjau ('document' => false) dengan keterangan bebas.
         * Hal yang sama berlaku untuk baris legalitas lingkungan pada tabel
         * administrasi.
         */
        'ISO45001' => [
            'administration' => [
                ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
                ['code' => 'nib', 'label' => 'NIB'],
                ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SMK3'],
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'environmental_legality_review', 'label' => 'Legalitas terkait dengan lingkungan (AMDAL, UKL/UPL/ SPPL)', 'document' => false, 'remark' => 'text'],
            ],
            'technical' => [
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'risk_management_file', 'label' => 'Risk management file'],
                ['code' => 'process_information', 'label' => 'Proses produksi / bisnis pada perusahaan beserta informasi terkait'],
                ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
                ['code' => 'environmental_risk_review', 'label' => 'Resiko dan peluang terkait lingkungan', 'document' => false, 'remark' => 'text'],
                ['code' => 'environmental_aspects_review', 'label' => 'Aspek lingkungan dan aspek penting lingkungan', 'document' => false, 'remark' => 'text'],
                ['code' => 'environmental_policy_review', 'label' => 'Kebijakan dan sasaran lingkungan', 'document' => false, 'remark' => 'text'],
                ['code' => 'emergency_response_review', 'label' => 'Dokumen tanggap darurat / emergency response plan', 'document' => false, 'remark' => 'text'],
            ],
        ],

        // FrM.9101/GIS — sistem manajemen lingkungan.
        'ISO14001' => [
            'administration' => [
                ['code' => 'siup', 'label' => 'SIUP (Surat Ijin Usaha / Industri)'],
                ['code' => 'company_deed', 'label' => 'Akte Pendirian Perusahaan'],
                ['code' => 'tdp', 'label' => 'TDP (Tanda Daftar Perusahaan)'],
                ['code' => 'application_letter', 'label' => 'Surat Permohonan Sertifikasi SM'],
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
                ['code' => 'previous_certificate', 'label' => 'Sertifikasi sistem manajemen (jika sudah tersertifikasi)'],
                ['code' => 'environmental_legality', 'label' => 'Legalitas terkait dengan lingkungan (AMDAL, UKL/UPL, SPPL)'],
            ],
            'technical' => [
                ['code' => 'application_form_signed', 'label' => 'Form Aplikasi permohonan sertifikasi FrM.9104/GIS yang telah terisi'],
                ['code' => 'system_manual', 'label' => 'Panduan Mutu / Daftar informasi terdokumentasi (optional)'],
                ['code' => 'master_document_list', 'label' => 'Daftar Induk Dokumen'],
                ['code' => 'organization_structure', 'label' => 'Struktur organisasi perusahaan'],
                ['code' => 'risk_management_file', 'label' => 'Risk management file'],
                ['code' => 'process_information', 'label' => 'Proses produksi / bisnis pada perusahaan beserta informasi terkait'],
                ['code' => 'internal_audit_evidence', 'label' => 'Dokumen internal audit dan tinjauan manajemen'],
                ['code' => 'previous_certificate', 'label' => 'Sertifikasi sistem manajemen (jika sudah tersertifikasi)'],
                ['code' => 'environmental_legality', 'label' => 'Legalitas terkait dengan lingkungan (AMDAL, UKL/UPL, SPPL)'],
                ['code' => 'environmental_risk', 'label' => 'Resiko dan peluang terkait lingkungan'],
                ['code' => 'aspect_identification_document', 'label' => 'Metode identifikasi dan kriteria penentuan aspek lingkungan penting'],
                ['code' => 'environmental_aspects', 'label' => 'Aspek lingkungan dan aspek penting lingkungan'],
                ['code' => 'environmental_policy_document', 'label' => 'Kebijakan dan sasaran lingkungan'],
                ['code' => 'environmental_program', 'label' => 'Program pencapaian sasaran lingkungan'],
                ['code' => 'emergency_response_plan', 'label' => 'Dokumen tanggap darurat / emergency response plan'],
            ],
        ],
    ],

    /*
     * Identitas formulir tinjauan per skema: judul kop, kode formulir, dan
     * lembaga sertifikasi yang disebut pada baris kesimpulan.
     *
     * Tata letaknya sendiri ditentukan review_template pada skema; yang berbeda
     * antar skema hanyalah teks-teks ini. Skema yang belum terdaftar memakai
     * nilai bawaan LSSM.
     */
    'form_meta' => [
        'ISO9001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSM',
            'code' => 'FrM.9107/GIS',
            'body' => 'LSSM',
            'mandays_text' => 'FrM.9105 / GIS (dilampirkan)',
        ],
        'ISO14001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSM',
            'code' => 'FrM.9101/GIS-5',
            'body' => 'LSSM',
            'mandays_text' => 'FrM.9105 / GIS (dilampirkan)',
        ],
        /*
         * Blok identitas FrM.9111 memuat dua baris yang tidak ada pada FrM.9107:
         * Tingkat Risiko (SMK3) dan Bahasa. Keduanya diisi klien pada form
         * permohonan, jadi cukup ditunjuk kode fieldnya lewat extra_identity.
         */
        'ISO45001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMK3',
            'code' => 'FrM.9111/GIS-0',
            'body' => 'LSSMK3',
            'mandays_text' => 'FrM.9105 / GIS (dilampirkan)',
            'extra_identity' => [
                ['label' => 'Tingkat Resiko (SMK3)', 'field' => 'k3_risk_level'],
                ['label' => 'Bahasa', 'field' => 'audit_language'],
            ],
        ],
        'ISO20000' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMLTI',
            'code' => 'FrM.9112/GIS-0',
            'body' => 'LSSMLTI',
            'mandays_text' => 'FrM.9113 / GIS (dilampirkan)',
        ],
        /*
         * Footer dokumen sumber FrM.9114 masih tertulis FrM.9112/GIS-0 — tampaknya
         * sisa salin dari formulir LSSMLTI. Yang dipakai di sini kode formulir yang
         * benar sesuai nama dan isinya.
         */
        'ISO22000' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMKP - LSHACCP',
            'code' => 'FrM.9114/GIS-0',
            'body' => 'LSSMKP / LSHACCP',
            'mandays_text' => 'FrM.9115 / GIS (dilampirkan)',
        ],
        // Berbagi formulir cetak FrM.9114 dengan ISO 22000; lihat $lssmkpRows.
        'HACCP' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMKP - LSHACCP',
            'code' => 'FrM.9114/GIS-0',
            'body' => 'LSSMKP / LSHACCP',
            'mandays_text' => 'FrM.9115 / GIS (dilampirkan)',
        ],
        // Kode formulirnya sama dengan LSSM lingkungan, tetapi revisi dan isinya berbeda.
        'ISO27001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMKI',
            'code' => 'FrM.9101/GIS-7',
            'body' => 'LSSMKI',
            'mandays_text' => 'FrM.9106 / GIS (dilampirkan)',
        ],
        'ISO37001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMAP',
            'code' => 'FrM.9108/GIS',
            'body' => 'LSSMAP',
            'mandays_text' => 'FrM.9109 / GIS (dilampirkan)',
        ],
    ],

    /*
     * ====== Struktur formulir FrO.7204/GIS-3 (Tinjauan Permohonan ISPO) ======
     *
     * Berbeda dari formulir LSSM yang memakai konvensi coretan, formulir ISPO
     * memakai kolom bercentang: Cukup / Belum Cukup, dengan Keterangan
     * berupa teks bebas yang diketik peninjau.
     *
     * Pembagian tugasnya mengikuti formulir: Admin mengisi bagian 1-3 dan
     * menandatangani sebagai Peninjau, Tim Teknis mengisi bagian 4-8 dan
     * menandatangani sebagai Approval.
     */
    'ispo' => [
        'form' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI ISPO',
            'subtitle' => 'Perusahaan Perkebunan/Integrasi | Pekebun | Industri Hilir | Bioenergi',
            'code' => 'FrO.7204/GIS-3',
            // Warna diambil dari berkas .docx aslinya.
            'heading_bg' => '00B050',
            'heading_text' => 'FFFFFF',
        ],

        /*
         * Hanya dua pilihan. Baris yang tidak berlaku bagi permohonan cukup
         * ditandai "Belum Cukup" beserta alasannya pada kolom Keterangan —
         * tidak ada kolom N/A tersendiri.
         */
        'result_options' => [
            'sufficient' => 'Cukup',
            'insufficient' => 'Belum Cukup',
        ],

        // Bagian 4.1 memakai istilah Lengkap/Belum Lengkap untuk hal yang sama.
        'completeness_options' => [
            'sufficient' => 'Lengkap',
            'insufficient' => 'Belum Lengkap',
        ],

        // Bagian 5 memakai istilah Memenuhi/Tidak Memenuhi.
        'capability_options' => [
            'sufficient' => 'Memenuhi',
            'insufficient' => 'Tidak Memenuhi',
        ],

        /*
         * Bagian 3 — checklist dokumen per ruang lingkup. Hanya kelompok yang
         * ruang lingkupnya dipilih pemohon yang ditinjau; kelompok lain tidak
         * ditampilkan, sesuai petunjuk pada formulir.
         *
         * 'scope' mengacu ke nilai applicant_type pada Form Aplikasi.
         */
        'document_groups' => [
            [
                'code' => 'perusahaan',
                'title' => '3.1 Perusahaan Perkebunan / Integrasi',
                'scope' => ['perusahaan_perkebunan'],
                'rows' => [
                    ['code' => 'app_letter', 'label' => 'Surat Permohonan Sertifikasi'],
                    ['code' => 'app_form', 'label' => 'Form Aplikasi ISPO FrO.7201/GIS-2.0'],
                    ['code' => 'plantation_license', 'label' => 'Perizinan berusaha perkebunan'],
                    ['code' => 'land_right_company', 'label' => 'Bukti hak atas tanah (HP/HGB/HGU/SHM)'],
                    ['code' => 'environmental_approval', 'label' => 'Persetujuan lingkungan'],
                    ['code' => 'internal_auditor_list_company', 'label' => 'Daftar minimal 2 auditor internal ISPO dan bukti pelatihan auditor ISPO'],
                    ['code' => 'estate_map', 'label' => 'Peta lokasi dan peta operasional kebun/PKS'],
                ],
            ],
            [
                'code' => 'pekebun',
                'title' => '3.2 Pekebun / Kelompok Pekebun',
                'scope' => ['pekebun_perorangan', 'kelompok_pekebun'],
                'rows' => [
                    ['code' => 'app_letter', 'label' => 'Surat Permohonan Sertifikasi'],
                    ['code' => 'app_form', 'label' => 'Form Aplikasi ISPO FrO.7201/GIS-2.0'],
                    ['code' => 'stdb', 'label' => 'STDB dan/atau dokumen pendaftaran usaha perkebunan'],
                    ['code' => 'land_right_smallholder', 'label' => 'Bukti kepemilikan/penguasaan atas tanah'],
                    ['code' => 'group_establishment', 'label' => 'Dokumen pembentukan Koperasi/Gapoktan/Poktan (untuk kelompok)'],
                    ['code' => 'member_list', 'label' => 'Daftar anggota dan daftar kebun anggota (untuk kelompok)'],
                    ['code' => 'ics_appointment', 'label' => 'Penetapan minimal 1 personel Sistem Kendali Internal/ICS dan bukti pelatihan/pemahaman ISPO'],
                    ['code' => 'sppl', 'label' => 'SPPL/persetujuan lingkungan apabila dipersyaratkan'],
                ],
            ],
            [
                'code' => 'hilir',
                'title' => '3.3 Industri Hilir Kelapa Sawit',
                'scope' => ['industri_hilir'],
                'rows' => [
                    ['code' => 'app_letter', 'label' => 'Surat Permohonan Sertifikasi'],
                    ['code' => 'app_form', 'label' => 'Form Aplikasi ISPO FrO.7201/GIS-2.0'],
                    ['code' => 'ispo_supplier_cert', 'label' => 'Sertifikat ISPO kegiatan usaha perkebunan atau sertifikat ISPO pemasok bahan baku'],
                    ['code' => 'company_deed_downstream', 'label' => 'Salinan akta pendirian perusahaan dan perubahannya'],
                    ['code' => 'downstream_license', 'label' => 'Perizinan berusaha Industri Hilir berdasarkan KBLI yang sesuai'],
                    ['code' => 'process_flow_downstream', 'label' => 'Diagram alir proses produksi'],
                    ['code' => 'facility_list_downstream', 'label' => 'Daftar fasilitas/sarana dan prasarana produksi'],
                    ['code' => 'internal_auditor_list_downstream', 'label' => 'Daftar auditor internal ISPO'],
                    ['code' => 'org_structure_downstream', 'label' => 'Struktur organisasi dan profil perusahaan'],
                    ['code' => 'iso_statement', 'label' => 'Sertifikat/pernyataan diri penerapan ISO 9001 dan/atau ISO 22000'],
                    ['code' => 'brand_cert', 'label' => 'Sertifikat merek atau bukti pendaftaran merek, jika memiliki merek'],
                ],
            ],
            [
                'code' => 'bioenergi',
                'title' => '3.4 Usaha Bioenergi Kelapa Sawit',
                'scope' => ['perusahaan_bioenergi'],
                'rows' => [
                    ['code' => 'app_letter', 'label' => 'Surat Permohonan Sertifikasi'],
                    ['code' => 'app_form', 'label' => 'Form Aplikasi ISPO FrO.7201/GIS-2.0'],
                    ['code' => 'bioenergy_license', 'label' => 'Perizinan berusaha untuk bahan bakar nabati, biomassa, dan/atau biogas'],
                    ['code' => 'ispo_estate_cert_bioenergy', 'label' => 'Sertifikat ISPO kegiatan usaha perkebunan; atau sertifikat ISPO Industri Hilir penghasil produk turunan apabila tidak memiliki sertifikat ISPO perkebunan'],
                    ['code' => 'bioenergy_process_flow', 'label' => 'Diagram alir proses produksi'],
                    ['code' => 'bioenergy_facility_list', 'label' => 'Daftar fasilitas produksi dan penyimpanan'],
                    ['code' => 'bioenergy_material_list', 'label' => 'Daftar sumber bahan baku dan pemasok'],
                    ['code' => 'bioenergy_supplier_cert', 'label' => 'Sertifikat ISPO pemasok terkait'],
                ],
            ],
        ],

        // Bagian 4 — kesesuaian data formulir aplikasi.
        'data_conformity' => [
            ['code' => 'a', 'label' => 'A. Jenis pemohon, ruang lingkup, keterkaitan usaha, dan jenis permohonan dipilih dengan jelas'],
            ['code' => 'b', 'label' => 'B. Identitas, bentuk badan hukum/kelembagaan, NIB, NPWP, alamat, PIC, dan kontak lengkap'],
            ['code' => 'c', 'label' => 'C. Sertifikat yang dimiliki dicantumkan beserta nomor, penerbit, ruang lingkup, dan masa berlaku'],
            ['code' => 'd', 'label' => 'D. Data personel, shift, sistem operasional, auditor internal, dan/atau personel ICS lengkap'],
            ['code' => 'e', 'label' => 'E. Seluruh lokasi/unit dicantumkan beserta alamat, koordinat, luas/kapasitas, dan status dalam ruang lingkup'],
            ['code' => 'k', 'label' => 'K. Pernyataan pemohon telah ditandatangani dan distempel'],
        ],

        // Bagian 4.1 — kelengkapan bagian khusus sesuai ruang lingkup.
        'section_completeness' => [
            ['code' => 'g', 'label' => 'G. Data Pekebun/Kelompok Pekebun'],
            ['code' => 'g1', 'label' => 'G.1 Daftar Kebun/Anggota'],
            ['code' => 'g2', 'label' => 'G.2 Ringkasan Penggunaan Lahan'],
            ['code' => 'g3', 'label' => 'G.3 Data Tanaman, Produksi, dan Kondisi Kebun'],
            ['code' => 'h', 'label' => 'H. Data Perusahaan Perkebunan'],
            ['code' => 'h1', 'label' => 'H.1 Legalitas dan Status Lahan'],
            ['code' => 'h2', 'label' => 'H.2 Data Kebun Inti dan Sumber TBS'],
            ['code' => 'h3', 'label' => 'H.3 Data PKS'],
            ['code' => 'i', 'label' => 'I. Data Industri Hilir'],
            ['code' => 'i1', 'label' => 'I.1 Produk dan Kapasitas'],
            ['code' => 'i2', 'label' => 'I.2 Bahan Baku dan Pemasok'],
            ['code' => 'i3', 'label' => 'I.3 Proses, Fasilitas, dan Alih Daya'],
            ['code' => 'j', 'label' => 'J. Data Usaha Bioenergi'],
            ['code' => 'j1', 'label' => 'J.1 Produk dan Kapasitas'],
            ['code' => 'j2', 'label' => 'J.2 Bahan Baku dan Ketertelusuran'],
        ],

        // Bagian 5 — kaji ulang kemampuan LS ISPO.
        'capability_review' => [
            ['code' => 'scope_accreditation', 'label' => 'Ruang lingkup permohonan berada dalam ruang lingkup akreditasi GIS'],
            ['code' => 'auditor_available', 'label' => 'Auditor/tenaga ahli yang kompeten sesuai sektor tersedia'],
            ['code' => 'reviewer_available', 'label' => 'Reviewer dan pengambil keputusan yang kompeten tersedia'],
            ['code' => 'location_clear', 'label' => 'Lokasi, jumlah unit, dan ruang lingkup dapat ditentukan dengan jelas'],
            ['code' => 'mandays_calculable', 'label' => 'Mandays dan sampling dapat dihitung'],
            ['code' => 'impartiality', 'label' => 'Risiko ketidakberpihakan dan konflik kepentingan dapat dikendalikan'],
            ['code' => 'resources', 'label' => 'Waktu, sumber daya, dan jadwal pelaksanaan tersedia'],
        ],

        // Bagian 6 — penetapan mandays audit.
        'mandays_sectors' => [
            ['code' => 'hulu_budidaya', 'label' => 'Hulu - Budi Daya'],
            ['code' => 'hulu_pks', 'label' => 'Hulu - Pengolahan Hasil/PKS'],
            ['code' => 'hulu_integrasi', 'label' => 'Hulu - Integrasi'],
            ['code' => 'pekebun', 'label' => 'Pekebun'],
            ['code' => 'industri_hilir', 'label' => 'Industri Hilir'],
            ['code' => 'bioenergi_bbn', 'label' => 'Bioenergi - BBN'],
            ['code' => 'bioenergi_biomassa', 'label' => 'Bioenergi - Biomassa'],
            ['code' => 'bioenergi_biogas', 'label' => 'Bioenergi - Biogas'],
        ],

        // Bagian 8 — hasil tinjauan permohonan.
        'decision_options' => [
            'approved' => 'Diterima',
            'returned' => 'Dikembalikan untuk dilengkapi',
            'rejected' => 'Ditolak',
        ],
        'reverification_options' => [
            'sufficient' => 'Memenuhi',
            'insufficient' => 'Tidak Memenuhi',
        ],
    ],

    // Dipakai bila skema belum punya entri form_meta.
    'form_meta_default' => [
        'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSM',
        'code' => 'FrM.9107/GIS',
        'body' => 'LSSM',
        'mandays_text' => 'FrM.9105 / GIS (dilampirkan)',
    ],

    // Kolom "Hasil Kajian*)" pada tabel administrasi dan teknis.
    'result_options' => [
        'sufficient' => 'Cukup',
        'insufficient' => 'Belum Cukup',
    ],

    // Kolom "Keterangan*)". Opsi tanggal dipakai baris seperti NIB.
    'remark_options' => [
        'sesuai' => 'Sesuai',
        'belum_sesuai' => 'Belum Sesuai',
        'tgl_berlaku' => 'Tgl Berlaku',
    ],

    /*
     * Blok identitas: pilihan yang dicetak berjajar dengan coretan. Sumber
     * nilainya isian klien (application_values), jadi peninjau tidak perlu
     * mengetik ulang. 'map' memetakan nilai form klien ke opsi formulir.
     */
    'identity_choices' => [
        'certification_type' => [
            'label' => 'Jenis Sertifikasi',
            'options' => ['Sertifikasi awal', 'Transfer', 'Perluasan'],
            'map' => [
                'initial' => 'Sertifikasi awal',
                'transfer' => 'Transfer',
                'scope_change' => 'Perluasan',
                // Di luar tiga opsi formulir; dicetak apa adanya tanpa coretan.
                'surveillance' => 'Surveillance',
                'recertification' => 'Resertifikasi',
            ],
        ],
        'site_type' => [
            'label' => 'Area Audit',
            'options' => ['Single Site', 'Multi site'],
            'map' => [
                'single' => 'Single Site',
                'multi' => 'Multi site',
            ],
        ],
        'audit_type' => [
            'label' => 'Jenis Audit',
            'options' => ['Single Audit', 'Combine Audit', 'Join Audit', 'Integrated Audit'],
            'map' => [
                'single' => 'Single Audit',
                'combined' => 'Combine Audit',
                'joint' => 'Join Audit',
                'integrated' => 'Integrated Audit',
            ],
        ],
    ],

    /*
     * Tiga baris kesimpulan pada bagian teknis yang juga dicetak dengan
     * coretan. Mandays punya perilaku khusus: selama belum ditentukan, sel
     * dicetak sebagai "FrM.9105 / GIS (dilampirkan)".
     */
    'technical_conclusion' => [
        'scope_conformity' => [
            // :body diganti nama lembaga sertifikasi dari form_meta (LSSM/LSSMLTI).
            'label' => 'Ruang lingkup sertifikasi sesuai dengan ruang lingkup :body',
            'options' => ['sesuai' => 'Sesuai', 'tidak_sesuai' => 'Tidak sesuai'],
        ],
        'audit_capability_choice' => [
            'label' => ':body dapat melakukan proses audit untuk pemohon (ruang lingkup, ketersediaan auditor, kesesuaian jadwal auditor)',
            'options' => ['dapat' => 'Dapat', 'tidak_dapat' => 'Tidak dapat'],
        ],
    ],

    // Baris keputusan: "Diterima / Ditolak *)".
    'decision_options' => [
        'approved' => 'Diterima',
        'rejected' => 'Ditolak',
    ],
];
