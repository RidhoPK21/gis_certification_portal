<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Role dan permission adalah struktur sistem,
         * sehingga aman dijalankan di semua environment.
         */
        $this->call([
            RolePermissionSeeder::class,
            SchemeCatalogSeeder::class,
            WorkflowSeeder::class,
        ]);

        /*
         * Akun demo hanya dibuat jika diaktifkan
         * melalui konfigurasi local/testing.
         */
        if (
            app()->environment(['local', 'testing']) &&
            config('systemgis.seed_demo_accounts')
        ) {
            $this->call([
                SystemAccountsSeeder::class,
            ]);
        }
    }
}