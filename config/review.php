<?php

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

        // FrM.9114/GIS — sistem manajemen keamanan pangan.
        'ISO22000' => [
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
        // Kode formulirnya sama dengan LSSM lingkungan, tetapi revisi dan isinya berbeda.
        'ISO27001' => [
            'title' => 'TINJAUAN PERMOHONAN SERTIFIKASI LSSMKI',
            'code' => 'FrM.9101/GIS-7',
            'body' => 'LSSMKI',
            'mandays_text' => 'FrM.9106 / GIS (dilampirkan)',
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
