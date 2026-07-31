<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('role_code', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_skippable')->default(false);
            $table->unsignedInteger('sla_days')->nullable();
            $table->json('entry_rules')->nullable();
            $table->json('completion_rules')->nullable();
            $table->timestamps();
            $table->unique(['workflow_template_id', 'code'], 'workflow_step_code_unique');
        });

        Schema::create('application_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('locked')->index();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('skip_reason')->nullable();
            $table->foreignId('skipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Nama eksplisit: nama otomatis Laravel mencapai 65 karakter,
            // melebihi batas identifier MySQL (64).
            $table->unique(['application_id', 'workflow_step_id'], 'application_workflow_step_unique');
        });

        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month')->default(0);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->string('format_pattern')->default('{SEQ}/{PREFIX}/{ROMAN_MONTH}/{YYYY}');
            $table->unsignedTinyInteger('padding')->default(3);
            $table->timestamps();
            $table->unique(['certification_scheme_id', 'year', 'month'], 'order_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
        Schema::dropIfExists('application_workflow_steps');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_templates');
    }
};
