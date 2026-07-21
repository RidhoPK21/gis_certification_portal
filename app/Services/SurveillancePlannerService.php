<?php

namespace App\Services;

use App\Models\CertificateFinal;
use App\Models\SurveillanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auditable rule-assisted planner. This intentionally does not market a
 * deterministic date formula as machine-learning "AI".
 */
class SurveillancePlannerService
{
    public function generate(CertificateFinal $certificate, ?int $userId = null): Collection
    {
        $certificate->loadMissing('application.scheme');
        $issued = CarbonImmutable::parse($certificate->issued_date);
        $expiry = $certificate->expiry_date ? CarbonImmutable::parse($certificate->expiry_date) : $issued->addYears(3);
        $intervalMonths = (int) config('gis.surveillance_interval_months', 12);
        $leadDays = (int) config('gis.surveillance_lead_days', 30);
        $maximumCycles = (int) config('gis.surveillance_max_cycles', 4);

        return DB::transaction(function () use ($certificate, $issued, $expiry, $intervalMonths, $leadDays, $maximumCycles, $userId) {
            $rows = collect();
            for ($cycle = 1; $cycle <= $maximumCycles; $cycle++) {
                $anniversary = $issued->addMonths($intervalMonths * $cycle);
                if ($anniversary->greaterThanOrEqualTo($expiry)) {
                    break;
                }

                // Plan ahead of the anniversary so scheduling can be coordinated.
                $planned = $anniversary->subDays($leadDays);
                $snapshot = [
                    'issued_date' => $issued->toDateString(),
                    'expiry_date' => $expiry->toDateString(),
                    'interval_months' => $intervalMonths,
                    'lead_days' => $leadDays,
                    'anniversary_date' => $anniversary->toDateString(),
                    'scheme_code' => $certificate->application->scheme->code,
                ];

                $rows->push(SurveillanceSchedule::updateOrCreate(
                    ['application_id' => $certificate->application_id, 'cycle' => $cycle],
                    [
                        'certificate_final_id' => $certificate->id,
                        'planned_date' => $planned->toDateString(),
                        'status' => 'planned',
                        'calculation_source' => 'rule_assisted',
                        'formula_version' => 'GIS-SURV-1.0',
                        'calculation_snapshot' => $snapshot,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                ));
            }

            return $rows;
        });
    }
}
