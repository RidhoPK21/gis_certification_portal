<?php

namespace App\Console\Commands;

use App\Models\CertificationApplication;
use App\Services\ReviewPdfService;
use Illuminate\Console\Command;

class GenerateReviewPdf extends Command
{
    protected $signature = 'gis:generate-review {application : Application ID, UUID, or order number}';

    protected $description = 'Generate a versioned Application Review PDF.';

    public function handle(ReviewPdfService $service): int
    {
        $key = $this->argument('application');
        $application = CertificationApplication::where('id', $key)->orWhere('uuid', $key)->orWhere('order_number', $key)->first();
        if (! $application) {
            $this->error('Permohonan tidak ditemukan.');

            return self::FAILURE;
        }
        $pdf = $service->generate($application);
        $this->info('PDF dibuat: storage/app/private/'.$pdf->file_path);

        return self::SUCCESS;
    }
}
