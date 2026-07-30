<?php

return [
    /*
     * Aspek yang dinilai pada Tinjauan Teknis permohonan.
     * Dipakai bersama oleh form tinjauan teknis (halaman Tim Teknis)
     * dan ringkasan read-only pada halaman Admin, agar tidak divergen.
     * key = kode field permohonan, value = label yang ditampilkan.
     */
    'technical_fields' => [
        'scope_review_result' => 'Kesesuaian ruang lingkup',
        'audit_capability' => 'Kemampuan GIS melakukan audit',
        'audit_mandays' => 'Mandays audit',
        'required_auditor_competence' => 'Kompetensi auditor',
        'assigned_auditor_team' => 'Tim auditor',
        'assigned_panelists' => 'Panelis',
    ],
];
