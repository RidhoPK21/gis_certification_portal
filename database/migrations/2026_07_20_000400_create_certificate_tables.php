<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name');
            $table->string('file_path');
            $table->string('checksum_sha256', 64);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'version']);
        });

        Schema::create('certificate_finals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('checksum_sha256', 64);
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->string('status', 30)->default('released')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('certificate_share_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_draft_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_final_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('link_type', 20);
            $table->string('token_hash', 64)->unique();
            $table->string('password_hash')->nullable();
            $table->dateTime('expires_at');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('max_access')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('certificate_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_share_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('action', 30)->default('preview');
            $table->boolean('is_success')->default(true);
            $table->string('failure_reason')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();
        });

        Schema::create('certificate_download_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_final_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_share_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_download_logs');
        Schema::dropIfExists('certificate_access_logs');
        Schema::dropIfExists('certificate_share_links');
        Schema::dropIfExists('certificate_finals');
        Schema::dropIfExists('certificate_drafts');
    }
};
