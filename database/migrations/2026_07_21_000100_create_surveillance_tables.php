<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveillance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_final_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('cycle');
            $table->date('planned_date');
            $table->date('scheduled_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->string('calculation_source', 40)->default('rule_assisted');
            $table->string('formula_version', 30)->default('GIS-SURV-1.0');
            $table->json('calculation_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveillance_schedules');
    }
};
