<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('module', 80)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table
                ->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'role_id',
                'user_id',
            ]);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table
                ->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'permission_id',
                'role_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};