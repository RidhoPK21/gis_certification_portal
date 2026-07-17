<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            [
                'email' => 'superadmin@systemgis.local',
            ],
            [
                'name' => 'Superadmin SystemGIS',
                'email_verified_at' => now(),
                'password' => 'SystemGIS!2026',
                'is_active' => true,
            ]
        );
    }
}