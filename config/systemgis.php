<?php

return [
    'company_name' => env(
        'SYSTEMGIS_COMPANY_NAME',
        'PT Global Inspeksi Sertifikasi'
    ),

    'company_short_name' => env(
        'SYSTEMGIS_COMPANY_SHORT_NAME',
        'GIS'
    ),

    /*
     * Akun demo hanya boleh dibuat pada local/testing.
     */
    'seed_demo_accounts' => filter_var(
        env('SYSTEMGIS_SEED_DEMO_ACCOUNTS', false),
        FILTER_VALIDATE_BOOL
    ),

    'demo_password' => env(
        'SYSTEMGIS_DEMO_PASSWORD'
    ),
];