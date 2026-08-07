<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_reviews', function (Blueprint $table): void {
            /*
             * Isian FrO.7204 yang tidak berbentuk baris kajian: tabel mandays
             * bagian 6, tanggal permintaan/batas/penerimaan kembali kelengkapan
             * dan hasil verifikasi ulang bagian 8, serta verifikasi kelengkapan
             * awal bagian 1.
             *
             * Disimpan sebagai JSON, bukan kolom terpisah, karena seluruhnya
             * khusus ISPO dan tidak dipakai formulir tinjauan skema lain.
             */
            $table->json('ispo_data')->nullable()->after('auditor_competence_codes');
        });
    }

    public function down(): void
    {
        Schema::table('application_reviews', function (Blueprint $table): void {
            $table->dropColumn('ispo_data');
        });
    }
};
