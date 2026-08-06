<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_items', function (Blueprint $table): void {
            /*
             * Kolom "Keterangan*)" pada FrM.9107 bukan teks bebas melainkan
             * pilihan (Sesuai / Belum Sesuai / Tgl Berlaku), dan opsi Tgl
             * Berlaku membutuhkan tanggal tersendiri. Kolom notes tetap ada
             * untuk catatan tambahan di luar pilihan tersebut.
             */
            $table->string('remark_option', 30)->nullable()->after('review_status');
            $table->date('remark_date')->nullable()->after('remark_option');
        });

        Schema::table('application_reviews', function (Blueprint $table): void {
            // Baris kesimpulan teknis FrM.9107 yang dicetak dengan coretan.
            $table->string('scope_conformity', 20)->nullable()->after('signed_name');
            $table->string('audit_capability_choice', 20)->nullable()->after('scope_conformity');
            // Isian "(jumlah site jika multi ____)" pada blok identitas.
            $table->unsignedSmallInteger('site_count')->nullable()->after('audit_capability_choice');
            /*
             * Panelis dipilih dari akun pengguna, bukan diketik, supaya nama yang
             * tercetak pada formulir selalu personel GIS yang benar-benar ada.
             * Auditor tidak disimpan di sini karena sudah punya audit_assignments
             * beserta peran LA/A/TA-nya.
             */
            $table->json('panelist_ids')->nullable()->after('site_count');
            /*
             * Kompetensi spesifik auditor (aspek 6.1–6.7 FrM.9101) yang
             * dicentang Tim Teknis untuk skema sistem manajemen lingkungan.
             */
            $table->json('auditor_competence_codes')->nullable()->after('panelist_ids');
        });
    }

    public function down(): void
    {
        Schema::table('application_reviews', function (Blueprint $table): void {
            $table->dropColumn(['scope_conformity', 'audit_capability_choice', 'site_count', 'panelist_ids', 'auditor_competence_codes']);
        });

        Schema::table('review_form_items', function (Blueprint $table): void {
            $table->dropColumn(['remark_option', 'remark_date']);
        });
    }
};
