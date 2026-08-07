<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheme_fields', function (Blueprint $table): void {
            /*
             * Definisi kolom untuk field bertipe 'table' dan 'repeatable'.
             *
             * Form Aplikasi ISPO (FrO.7201) memuat banyak tabel: sebagian
             * barisnya tetap (mis. H.1 Legalitas: APL, HPK, HGU/HP, ...) dan
             * sebagian ditambah sendiri oleh pemohon (mis. G.1 Daftar Kebun).
             * Keduanya butuh daftar kolom, yang tidak bisa diwakili oleh
             * scheme_field_options karena satu opsi hanya punya value + label.
             *
             * Bentuk: [{"code": "...", "label": "...", "type": "text|number|date"}]
             */
            $table->json('column_definitions')->nullable()->after('options_source');

            /*
             * Baris tetap untuk field bertipe 'table'. Bentuknya sama dengan
             * kolom: [{"code": "...", "label": "..."}]. Kosong untuk
             * 'repeatable', karena barisnya berasal dari pemohon.
             */
            $table->json('row_definitions')->nullable()->after('column_definitions');
        });
    }

    public function down(): void
    {
        Schema::table('scheme_fields', function (Blueprint $table): void {
            $table->dropColumn(['column_definitions', 'row_definitions']);
        });
    }
};
