<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('review_type', 30)->default('administration');
            $table->unsignedInteger('round')->default(1);
            $table->string('status', 30)->default('in_progress');
            $table->longText('notes')->nullable();
            $table->longText('rejection_reason')->nullable();
            $table->date('action_date')->nullable();
            $table->string('signed_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['application_id', 'review_type']);
        });

        Schema::create('review_form_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_review_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->string('item_code', 100);
            $table->string('item_label');
            $table->string('presence_status', 30)->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['application_review_id', 'item_type', 'item_code'], 'review_item_unique');
        });

        Schema::create('generated_pdfs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('template_code', 50);
            $table->unsignedInteger('template_version')->default(1);
            $table->unsignedInteger('document_version')->default(1);
            $table->string('file_path');
            $table->string('checksum_sha256', 64);
            $table->json('source_snapshot')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_pdfs');
        Schema::dropIfExists('review_form_items');
        Schema::dropIfExists('application_reviews');
    }
};
