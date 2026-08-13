<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruang lingkup akreditasi menurut KAN K-07.01 Rev.2 Lampiran 1: kode IAF
 * beserta kode NACE di bawahnya.
 *
 * Klien memilih dari daftar ini, tidak lagi mengetik bebas, sehingga kode yang
 * tercatat selalu berasal dari acuan resmi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iaf_codes', function (Blueprint $table): void {
            $table->id();
            // "7a" dan "7b" adalah kode sah pada Lampiran 1, jadi bukan integer.
            $table->string('code', 10)->unique();
            $table->string('name_en');
            $table->string('name_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nace_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iaf_code_id')->constrained('iaf_codes')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name_en', 500);
            // Sebagian keterangan memuat kalimat pengecualian yang panjang.
            $table->text('name_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            /*
             * Kode NACE tidak unik secara global — NACE 17 milik IAF 7a maupun
             * 7b — sehingga keunikannya hanya berlaku di dalam satu IAF.
             */
            $table->unique(['iaf_code_id', 'code'], 'nace_code_per_iaf_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nace_codes');
        Schema::dropIfExists('iaf_codes');
    }
};
