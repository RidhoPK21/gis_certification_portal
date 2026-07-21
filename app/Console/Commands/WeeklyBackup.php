<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class WeeklyBackup extends Command
{
    protected $signature = 'gis:weekly-backup';

    protected $description = 'Create a timestamped database and private-file backup package.';

    public function handle(): int
    {
        $stamp = now()->format('Ymd_His');
        $backupRoot = storage_path('app/private/backups');
        $workDir = $backupRoot.'/'.$stamp;
        File::ensureDirectoryExists($workDir);

        if (! $this->dumpDatabase($workDir)) {
            return self::FAILURE;
        }

        foreach (['applications', 'generated', 'certificates'] as $directory) {
            $source = storage_path('app/private/'.$directory);
            if (File::isDirectory($source)) {
                File::copyDirectory($source, $workDir.'/private-files/'.$directory);
            }
        }

        file_put_contents($workDir.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'database' => config('database.default'),
            'contents' => ['database', 'private-files/applications', 'private-files/generated', 'private-files/certificates'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (class_exists(ZipArchive::class)) {
            $zipPath = $workDir.'.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error('Gagal membuat arsip ZIP backup.');

                return self::FAILURE;
            }
            foreach (File::allFiles($workDir) as $file) {
                $zip->addFile($file->getRealPath(), $file->getRelativePathname());
            }
            $zip->close();
            File::deleteDirectory($workDir);
            $this->info('Backup lengkap: '.$zipPath);
        } else {
            $this->warn('Ekstensi zip tidak tersedia; backup lengkap disimpan sebagai folder: '.$workDir);
        }

        return self::SUCCESS;
    }

    private function dumpDatabase(string $workDir): bool
    {
        $connection = config('database.default');
        $db = config("database.connections.$connection");
        if ($connection === 'sqlite') {
            if (! File::exists($db['database'])) {
                $this->error('Database SQLite tidak ditemukan.');

                return false;
            }
            File::copy($db['database'], $workDir.'/database.sqlite');

            return true;
        }

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            $this->error('Driver database belum didukung oleh command backup.');

            return false;
        }

        $process = new Process([
            'mysqldump', '--single-transaction', '--routines', '--triggers',
            '--host='.$db['host'], '--port='.(string) $db['port'],
            '--user='.$db['username'], $db['database'],
        ]);
        $process->setEnv(['MYSQL_PWD' => (string) $db['password']]);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('mysqldump gagal: '.$process->getErrorOutput());

            return false;
        }
        file_put_contents($workDir.'/database.sql', $process->getOutput());

        return true;
    }
}
