<?php

return [
    [
        'section' => 'Utama',
        'label' => 'Dashboard',
        'route' => 'dashboard',
        'active' => 'dashboard',
        'roles' => [
            'client',
            'admin_application',
            'admin_sustain',
            'finance',
            'auditor',
            'technical',
            'technical_sustain',
            'superadmin',
        ],
    ],
    [
        'section' => 'Utama',
        'label' => 'Panduan Penggunaan',
        'route' => 'panduan',
        'active' => 'panduan',
        'roles' => [
            'client',
            'admin_application',
            'admin_sustain',
            'finance',
            'auditor',
            'technical',
            'technical_sustain',
            'superadmin',
        ],
    ],

    [
        'section' => 'Permohonan Klien',
        'label' => 'Ajukan Sertifikasi',
        'route' => 'client.applications.schemes',
        'active' => [
            'client.applications.schemes',
            'client.applications.create',
            'client.applications.store',
        ],
        'roles' => ['client'],
    ],
    [
        'section' => 'Permohonan Klien',
        'label' => 'Permohonan Saya',
        'route' => 'client.applications.index',
        'active' => [
            'client.applications.index',
            'client.applications.show',
            'client.applications.edit',
            'client.applications.update',
            'client.applications.submit',
        ],
        'roles' => ['client'],
    ],
    [
        'section' => 'Permohonan Klien',
        'label' => 'Corrective Action',
        'route' => 'client.corrective-actions.index',
        'active' => 'client.corrective-actions.*',
        'roles' => ['client'],
    ],

    [
        'section' => 'Admin Permohonan',
        'label' => 'Review Permohonan',
        'route' => 'internal.applications.index',
        'active' => 'internal.applications.*',
        'roles' => [
            'admin_application',
            'admin_sustain',
            'superadmin',
        ],
        'section_by_role' => ['admin_sustain' => 'Tim Admin Sustain'],
    ],
    [
        'section' => 'Admin Permohonan',
        'label' => 'Permintaan Formulir GIS',
        'route' => 'internal.gis-form-requests.index',
        'active' => 'internal.gis-form-requests.*',
        'roles' => [
            'admin_application',
            'admin_sustain',
            'superadmin',
        ],
        'section_by_role' => ['admin_sustain' => 'Tim Admin Sustain'],
    ],

    [
        'section' => 'Finance',
        'label' => 'Invoice & Pembayaran',
        'route' => 'finance.index',
        'active' => 'finance.*',
        'roles' => [
            'finance',
            'superadmin',
        ],
    ],

    [
        'section' => 'Audit',
        'label' => 'Audit & Corrective Action',
        'route' => 'audit.index',
        'active' => 'audit.*',
        'roles' => [
            'auditor',
            'superadmin',
        ],
    ],

    [
        'section' => 'Tim Teknis',
        'label' => 'Tinjauan Teknis',
        'route' => 'technical.reviews.index',
        'active' => 'technical.reviews.*',
        'roles' => [
            'technical',
            'technical_sustain',
            'superadmin',
        ],
        'section_by_role' => ['technical_sustain' => 'Tim Teknis Sustain'],
    ],

    [
        'section' => 'Tim Teknis',
        'label' => 'Penugasan & Surat Tugas',
        'route' => 'technical.assignments.index',
        'active' => 'technical.assignments.*',
        'roles' => [
            'technical',
            'technical_sustain',
            'superadmin',
        ],
        'section_by_role' => ['technical_sustain' => 'Tim Teknis Sustain'],
    ],

    [
        'section' => 'Tim Teknis',
        'label' => 'Sertifikat',
        'route' => 'technical.index',
        'active' => [
            'technical.index',
            'technical.show',
            'technical.draft.*',
            'technical.final.*',
            'technical.complete',
            'technical.link.*',
            'technical.surveillance.*',
        ],
        'roles' => [
            'technical',
            'technical_sustain',
            'superadmin',
        ],
        'section_by_role' => ['technical_sustain' => 'Tim Teknis Sustain'],
    ],

    [
        'section' => 'Superadmin',
        'label' => 'User & Role',
        'route' => 'superadmin.users.index',
        'active' => 'superadmin.users.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Master Skema',
        'route' => 'superadmin.schemes.index',
        'active' => 'superadmin.schemes.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Formulir Wajib GIS',
        'route' => 'superadmin.gis-forms.index',
        'active' => 'superadmin.gis-forms.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Produk SNI',
        'route' => 'superadmin.sni-products.index',
        'active' => 'superadmin.sni-products.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Produk & Kategori SNI',
        'route' => 'superadmin.sni-taxonomy.index',
        'active' => 'superadmin.sni-taxonomy.*',
        'roles' => ['superadmin'],
    ],

    [
        'section' => 'Superadmin',
        'label' => 'Kode IAF & NACE',
        'route' => 'superadmin.iaf-nace.index',
        'active' => 'superadmin.iaf-nace.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Audit Trail',
        'route' => 'superadmin.audit-trail.index',
        'active' => 'superadmin.audit-trail.*',
        'roles' => ['superadmin'],
    ],
    [
        'section' => 'Superadmin',
        'label' => 'Pengaturan Sistem',
        'route' => 'superadmin.settings.index',
        'active' => 'superadmin.settings.*',
        'roles' => ['superadmin'],
    ],

    [
        'section' => 'Akun',
        'label' => 'Profil',
        'route' => 'profile.edit',
        'active' => 'profile.*',
        'roles' => [
            'client',
            'admin_application',
            'admin_sustain',
            'finance',
            'auditor',
            'technical',
            'technical_sustain',
            'superadmin',
        ],
    ],
];