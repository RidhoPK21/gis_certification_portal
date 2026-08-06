<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheme_required_documents', function (Blueprint $table): void {
            /*
             * Memisahkan formulir terbitan LS (yang templatenya harus diminta
             * ke GIS) dari dokumen milik perusahaan. Dipakai untuk penomoran
             * terpisah pada checklist dan untuk mengunci unggahan sampai
             * permintaan template disetujui.
             */
            $table->string('document_group', 20)->default('company')->after('requirement')->index();
        });

        Schema::create('gis_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_required_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Nama eksplisit: nama otomatis Laravel melewati batas 64 karakter MySQL.
            $table->unique(['certification_scheme_id', 'code'], 'gis_form_template_code_unique');
        });

        Schema::create('gis_form_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('client_note')->nullable();
            $table->text('response_note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'status'], 'gis_form_request_application_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_form_requests');
        Schema::dropIfExists('gis_form_templates');

        Schema::table('scheme_required_documents', function (Blueprint $table): void {
            $table->dropIndex(['document_group']);
            $table->dropColumn('document_group');
        });
    }
};
