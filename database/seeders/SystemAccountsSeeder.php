<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SystemAccountsSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Jangan pernah membuat akun demo otomatis
         * pada staging atau production.
         */
        if (
            ! app()->environment(['local', 'testing']) ||
            ! config('systemgis.seed_demo_accounts')
        ) {
            $this->command?->warn(
                'Pembuatan akun demo dilewati.'
            );

            return;
        }

        $password = config('systemgis.demo_password');

        if (
            ! is_string($password) ||
            trim($password) === ''
        ) {
            throw new RuntimeException(
                'SYSTEMGIS_DEMO_PASSWORD belum diisi pada file .env.'
            );
        }

        $accounts = [
            [
                'role' => 'client',
                'name' => 'Klien Demo GIS',
                'email' => 'client@systemgis.local',
            ],
            [
                'role' => 'admin_application',
                'name' => 'Admin Permohonan GIS',
                'email' => 'admin.application@systemgis.local',
            ],
            [
                'role' => 'finance',
                'name' => 'Finance GIS',
                'email' => 'finance@systemgis.local',
            ],
            [
                'role' => 'auditor',
                'name' => 'Auditor GIS',
                'email' => 'auditor@systemgis.local',
            ],
            [
                'role' => 'technical',
                'name' => 'Tim Teknis GIS',
                'email' => 'technical@systemgis.local',
            ],
            [
                'role' => 'superadmin',
                'name' => 'Superadmin SystemGIS',
                'email' => 'superadmin@systemgis.local',
            ],
        ];

        foreach ($accounts as $account) {
            $role = Role::query()
                ->where('code', $account['role'])
                ->where('is_active', true)
                ->firstOrFail();

            $user = User::query()->updateOrCreate(
                [
                    'email' => $account['email'],
                ],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            $user->roles()->sync([
                $role->id,
            ]);
        }
    }
}