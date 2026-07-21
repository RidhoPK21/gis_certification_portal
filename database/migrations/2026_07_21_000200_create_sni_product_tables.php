<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sni_product_master', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('category')->nullable();
            $table->string('sni_number')->nullable();
            $table->string('certification_system')->default('System 5');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sni_product_document_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sni_product_master_id')->constrained('sni_product_master')->cascadeOnDelete();
            $table->string('document_code');
            $table->string('document_name');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sni_product_document_rules');
        Schema::dropIfExists('sni_product_master');
    }
};
