<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role', 20)->default('A');
            $table->string('stage_code', 30)->default('all');
            $table->date('assigned_date');
            $table->string('status', 30)->default('assigned');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'auditor_id', 'stage_code'], 'audit_assignment_unique');
        });

        Schema::create('audit_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('stage_code', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->date('audit_date')->nullable();
            $table->decimal('mandays', 7, 2)->nullable();
            $table->text('auditor_team')->nullable();
            $table->longText('summary')->nullable();
            $table->text('skip_reason')->nullable();
            $table->date('action_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'stage_code']);
        });

        Schema::create('audit_stage_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_stage_id')->constrained()->cascadeOnDelete();
            $table->string('file_type', 50)->default('report');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('finding_number', 60);
            $table->string('finding_type', 30)->default('minor');
            $table->string('clause_reference')->nullable();
            $table->longText('description');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'finding_number']);
        });

        Schema::create('corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->longText('root_cause')->nullable();
            $table->longText('correction')->nullable();
            $table->longText('corrective_action')->nullable();
            $table->date('implementation_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('corrective_action_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('corrective_action_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('corrective_action_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('corrective_action_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->longText('notes')->nullable();
            $table->date('action_date');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_action_reviews');
        Schema::dropIfExists('corrective_action_files');
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('audit_notes');
        Schema::dropIfExists('audit_stage_files');
        Schema::dropIfExists('audit_stages');
        Schema::dropIfExists('audit_assignments');
    }
};
