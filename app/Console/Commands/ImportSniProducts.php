<?php

namespace App\Console\Commands;

use App\Services\SniProductImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ImportSniProducts extends Command
{
    protected $signature = 'gis:import-sni-products {file : Absolute or relative CSV/XLSX path}';

    protected $description = 'Import and synchronize the SNI product master.';

    public function handle(SniProductImportService $service): int
    {
        $path = realpath($this->argument('file'));
        if (! $path || ! is_file($path)) {
            $this->error('File tidak ditemukan.');

            return self::FAILURE;
        }
        $file = new UploadedFile($path, basename($path), null, null, true);
        $result = $service->import($file);
        $this->table(['Baru', 'Diperbarui', 'Dilewati'], [[$result['created'], $result['updated'], $result['skipped']]]);

        return self::SUCCESS;
    }
}
