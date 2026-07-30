<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstallApplication extends Command
{
    protected $signature = 'gis:install {--fresh : Drop all tables before installation} {--demo : Seed demo accounts and sample application} {--force : Run without production confirmation}';

    protected $description = 'Install the standalone GIS Certification Portal database and storage structure.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm('Aplikasi berada pada mode production. Lanjutkan instalasi?')) {
            return self::FAILURE;
        }

        foreach (['applications', 'generated/reviews', 'certificates', 'backups'] as $dir) {
            if (! is_dir(storage_path('app/private/'.$dir))) {
                mkdir(storage_path('app/private/'.$dir), 0775, true);
            }
        }

        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $args = ['--force' => true];
        if ($this->option('demo')) {
            $args['--seed'] = true;
        }
        $exit = Artisan::call($command, $args);
        $this->output->write(Artisan::output());
        if ($exit !== 0) {
            return self::FAILURE;
        }

        if (! $this->option('demo')) {
            foreach (['RolePermissionSeeder', 'SchemeCatalogSeeder', 'WorkflowSeeder'] as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                $this->output->write(Artisan::output());
            }
            $email = trim((string) env('GIS_ADMIN_EMAIL'));
            if ($email === '') {
                $email = trim((string) $this->ask('Email superadmin (email asli, dipakai untuk login)'));
            }

            $emailCheck = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
            if ($emailCheck->fails()) {
                $this->components->error('Email superadmin tidak valid. Isi GIS_ADMIN_EMAIL pada .env atau masukkan email yang benar.');

                return self::FAILURE;
            }

            $password = (string) env('GIS_ADMIN_PASSWORD');
            if ($password === '') {
                $password = Str::password(18, true, true, false, false);
                $this->components->warn('Password superadmin awal (hanya ditampilkan sekali ini, simpan sekarang): '.$password);
            }
            $admin = User::updateOrCreate(['email' => $email], [
                'name' => env('GIS_ADMIN_NAME', 'Super Administrator GIS'),
                'company_name' => 'PT Global Inspeksi Sertifikasi',
                'job_title' => 'Superadmin',
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            /*
             * email_verified_at bukan kolom fillable, jadi harus di-set
             * lewat forceFill; tanpa ini superadmin tidak bisa login
             * karena login memblokir email yang belum terverifikasi.
             */
            $admin->forceFill(['email_verified_at' => now()])->save();

            $admin->roles()->sync([Role::where('code', 'superadmin')->value('id')]);
            $this->info('Akun superadmin: '.$email);
        }

        $this->components->info('Instalasi selesai. Jalankan php artisan serve untuk uji lokal atau arahkan document root hosting ke folder public.');

        return self::SUCCESS;
    }
}
