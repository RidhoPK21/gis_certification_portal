<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->unsignedTinyInteger('current_milestone')->default(0);
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('milestone')->default(1);
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->string('status', 30)->default('verified');
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedTinyInteger('milestone')->default(0);
            $table->dateTime('action_date');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_history');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
    }
};
