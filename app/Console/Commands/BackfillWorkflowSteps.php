<?php

namespace App\Console\Commands;

use App\Models\CertificationApplication;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

class BackfillWorkflowSteps extends Command
{
    protected $signature = 'workflow:backfill-steps {--dry-run : Tampilkan rencana tanpa menulis apa pun}';

    protected $description = 'Mengisi application_workflow_steps untuk order yang belum memilikinya (mis. di-submit sebelum WorkflowSeeder dijalankan).';

    public function handle(WorkflowService $workflow): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $filled = 0;
        $blocked = [];

        CertificationApplication::query()
            ->whereNotNull('submitted_at')
            ->whereDoesntHave('workflowSteps')
            ->with('scheme')
            ->chunkById(100, function ($applications) use ($workflow, $dryRun, &$filled, &$blocked): void {
                foreach ($applications as $application) {
                    /*
                     * WorkflowService::initialize() diam-diam return bila skema
                     * tidak punya template aktif. Di sini justru harus dilaporkan,
                     * karena itulah akar masalah yang selama ini tersembunyi.
                     */
                    $hasTemplate = WorkflowTemplate::where('certification_scheme_id', $application->certification_scheme_id)
                        ->where('is_active', true)
                        ->exists();

                    if (! $hasTemplate) {
                        $blocked[$application->scheme?->short_name ?? 'Skema #'.$application->certification_scheme_id][] = $application->order_number;

                        continue;
                    }

                    if (! $dryRun) {
                        $workflow->initialize($application);
                    }

                    $filled++;
                    $this->line(($dryRun ? '[rencana] ' : '[selesai] ').$application->order_number);
                }
            });

        $this->newLine();
        $this->info($dryRun
            ? $filled.' order akan diisi (dry-run, tidak ada perubahan).'
            : $filled.' order berhasil diisi.');

        if ($blocked !== []) {
            $this->newLine();
            $this->error('Order berikut tidak dapat diisi karena skemanya belum punya WorkflowTemplate aktif:');

            foreach ($blocked as $scheme => $orders) {
                $this->line('  '.$scheme.': '.implode(', ', $orders));
            }

            $this->newLine();
            $this->warn('Jalankan: php artisan db:seed --class=WorkflowSeeder');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
