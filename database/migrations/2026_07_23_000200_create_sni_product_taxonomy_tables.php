<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sni_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('mandatory_type', 20)->nullable(); // wajib | sukarela
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sni_product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sni_product_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['sni_product_group_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sni_product_categories');
        Schema::dropIfExists('sni_product_groups');
    }
};
