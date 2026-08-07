<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheme_sections', function (Blueprint $table): void {
            /*
             * Form Aplikasi ISPO (FrO.7201) memuat empat bagian besar yang hanya
             * berlaku untuk ruang lingkup tertentu — G Pekebun, H Perusahaan
             * Perkebunan, I Industri Hilir, J Bioenergi. Menyembunyikannya
             * lewat kondisi per-field saja tidak cukup: judul bagiannya tetap
             * tercetak tanpa isi. Karena itu kondisinya dipasang pada section.
             */
            $table->json('conditional_rules')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('scheme_sections', function (Blueprint $table): void {
            $table->dropColumn('conditional_rules');
        });
    }
};
