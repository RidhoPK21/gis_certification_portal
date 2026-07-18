<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('company_name', 200)->nullable()->after('phone');
            $table->string('job_title', 100)->nullable()->after('company_name');
            $table->string('locale', 10)->default('id')->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'company_name',
                'job_title',
                'locale',
            ]);
        });
    }
};
