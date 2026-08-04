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
        'scope_review_result' => [
            'label' => 'Kesesuaian ruang lingkup',
            'input' => 'textarea',
        ],
        'audit_capability' => [
            'label' => 'Kemampuan GIS melakukan audit',
            'input' => 'textarea',
        ],
        'audit_mandays' => [
            'label' => 'Mandays audit (Stage 1 + Stage 2)',
            'input' => 'number',
        ],
        'required_auditor_competence' => [
            'label' => 'Kompetensi auditor',
            'input' => 'textarea',
        ],
        'specific_auditor_competence' => [
            'label' => 'Kompetensi spesifik',
            'input' => 'textarea',
        ],
        'assigned_auditor_team' => [
            'label' => 'Tim auditor (LA, A, TA)',
            'input' => 'text',
        ],
        'assigned_panelists' => [
            'label' => 'Panelis yang ditugaskan',
            'input' => 'text',
        ],
    ],
];
