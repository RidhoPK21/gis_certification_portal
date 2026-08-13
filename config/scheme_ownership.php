<?php

/*
 * Pembagian tanggung jawab skema antar tim internal.
 *
 * ISPO dikerjakan tim tersendiri — Tim Admin Sustain dan Tim Teknis Sustain —
 * sedangkan skema lain tetap milik Admin Permohonan dan Tim Teknis. Pemetaan
 * ini menjadi satu-satunya sumber kebenaran: antrean, penjagaan halaman, dan
 * tujuan notifikasi semuanya membacanya dari sini.
 *
 * Kuncinya review_template, bukan category. Keduanya kebetulan setara untuk
 * ISPO, tetapi review_template sudah menjadi poros percabangan di seluruh
 * service (ReviewPdfService, AssignmentLetterService, IspoReviewService).
 *
 * Satu akun boleh memegang lebih dari satu role; cakupannya menjadi gabungan,
 * sehingga pemegang admin_application sekaligus admin_sustain melihat seluruh
 * skema.
 */

return [

    'by_template' => [
        'ispo' => [
            'admin' => 'admin_sustain',
            'technical' => 'technical_sustain',
        ],
    ],

    /*
     * Dipakai seluruh review_template yang tidak disebut di atas — lssm, lsml,
     * dan sni.
     */
    'default' => [
        'admin' => 'admin_application',
        'technical' => 'technical',
    ],

    /*
     * Role yang melihat seluruh skema tanpa penyaringan.
     */
    'unrestricted_roles' => ['superadmin'],
];
