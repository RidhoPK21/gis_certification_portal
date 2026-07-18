<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->nullable()->unique();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('certification_scheme_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('form_version')->default(1);
            $table->json('form_snapshot')->nullable();
            $table->string('status', 60)->default('draft')->index();
            $table->string('current_step', 80)->default('application_form');
            $table->string('company_name');
            $table->string('applicant_name')->nullable();
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            $table->date('order_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('application_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_code', 100);
            $table->longText('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->string('field_label_snapshot')->nullable();
            $table->string('field_type_snapshot', 30)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'field_code']);
        });

        Schema::create('application_repeatable_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('group_code', 100);
            $table->uuid('row_uuid');
            $table->json('data');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['application_id', 'row_uuid']);
            $table->index(['application_id', 'group_code']);
        });

        Schema::create('application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_required_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_code', 100);
            $table->string('document_name');
            $table->string('review_status', 30)->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'document_code']);
        });

        Schema::create('application_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->boolean('is_current')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['application_document_id', 'version'], 'document_version_unique');
        });

        Schema::create('application_revision_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_round')->default(1);
            $table->string('target_type', 30);
            $table->string('target_code', 100);
            $table->string('target_label');
            $table->text('revision_note');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60);
            $table->string('action', 100);
            $table->text('notes')->nullable();
            $table->dateTime('action_date');
            $table->dateTime('system_recorded_at');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'action_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_history');
        Schema::dropIfExists('application_revision_items');
        Schema::dropIfExists('application_document_versions');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_repeatable_rows');
        Schema::dropIfExists('application_values');
        Schema::dropIfExists('applications');
    }
};
