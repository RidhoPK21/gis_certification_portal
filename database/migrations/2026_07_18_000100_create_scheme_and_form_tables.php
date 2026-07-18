<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_schemes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name', 100);
            $table->string('category', 40)->default('management_system')->index();
            $table->string('standard')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('form_version')->default(1);
            $table->string('order_prefix', 40)->nullable();
            $table->string('review_template', 40)->default('lssm');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('scheme_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['certification_scheme_id', 'code'], 'scheme_section_code_unique');
        });

        Schema::create('scheme_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_section_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('label');
            $table->string('type', 30)->default('text');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('conditional_rules')->nullable();
            $table->string('options_source')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['scheme_section_id', 'code'], 'scheme_field_code_unique');
            $table->index('code');
        });

        Schema::create('scheme_field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_field_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scheme_required_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('requirement', 20)->default('required');
            $table->json('conditional_rules')->nullable();
            $table->json('allowed_extensions')->nullable();
            $table->unsignedInteger('max_size_mb')->default(20);
            $table->string('review_group', 30)->default('administration');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['certification_scheme_id', 'code'], 'scheme_document_code_unique');
        });

        Schema::create('form_configuration_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_scheme_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['certification_scheme_id', 'version'], 'scheme_form_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_configuration_versions');
        Schema::dropIfExists('scheme_required_documents');
        Schema::dropIfExists('scheme_field_options');
        Schema::dropIfExists('scheme_fields');
        Schema::dropIfExists('scheme_sections');
        Schema::dropIfExists('certification_schemes');
    }
};
