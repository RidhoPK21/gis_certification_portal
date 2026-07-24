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
            'finance',
            'auditor',
            'technical',
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
            'superadmin',
        ],
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
        'label' => 'Sertifikat',
        'route' => 'technical.index',
        'active' => 'technical.*',
        'roles' => [
            'technical',
            'superadmin',
        ],
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
        'label' => 'Notifikasi',
        'route' => 'notifications.index',
        'active' => 'notifications.*',
        'roles' => [
            'client',
            'admin_application',
            'finance',
            'auditor',
            'technical',
            'superadmin',
        ],
    ],
    [
        'section' => 'Akun',
        'label' => 'Profil',
        'route' => 'profile.edit',
        'active' => 'profile.*',
        'roles' => [
            'client',
            'admin_application',
            'finance',
            'auditor',
            'technical',
            'superadmin',
        ],
    ],
];