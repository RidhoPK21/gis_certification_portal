<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Tugas auditor, satu baris per tahap audit.
 *
 * Baris ini menyimpan masukan yang masih bisa diedit Tim Teknis (nomor,
 * tanggal, tabel auditor, isian per keluarga skema, tanda tangan berstempel).
 * Berkas PDF hasil render tetap dicatat di generated_pdfs supaya riwayat versi,
 * checksum, dan route unduhnya memakai jalur yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('stage_code', 20);
            // Cadangan untuk surat pada siklus surveillance berikutnya.
            $table->unsignedTinyInteger('cycle')->default(0);

            $table->string('template_code', 20);
            $table->string('number_family', 20);
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('letter_number', 100)->nullable();
            $table->string('letter_place', 80)->default('Tangerang');
            $table->date('letter_date')->nullable();
            $table->date('assignment_start_date')->nullable();
            $table->date('assignment_end_date')->nullable();

            // Dibekukan saat disimpan agar surat yang sudah terbit tidak ikut
            // berubah ketika tim tahap lain diganti.
            $table->json('auditor_rows')->nullable();
            $table->json('field_overrides')->nullable();

            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();

            // Tanda tangan berstempel khusus Surat Tugas; PDF tinjauan tetap
            // memakai tanda tangan dari profil akun.
            $table->string('signature_path')->nullable();
            $table->string('signature_original_name')->nullable();
            $table->string('signature_mime', 120)->nullable();
            $table->unsignedBigInteger('signature_size_bytes')->nullable();
            $table->foreignId('signature_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signature_uploaded_at')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedInteger('pdf_version')->default(0);
            $table->foreignId('generated_pdf_id')->nullable()->constrained('generated_pdfs')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'stage_code', 'cycle'], 'assignment_letter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_letters');
    }
};
