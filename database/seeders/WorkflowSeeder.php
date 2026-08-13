<?php

namespace Database\Seeders;

use App\Models\CertificationScheme;
use App\Models\WorkflowTemplate;
use App\Services\SchemeOwnershipService;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $ownership = app(SchemeOwnershipService::class);

        foreach (CertificationScheme::all() as $scheme) {
            /*
             * Langkah admin dan teknis dipegang tim yang memiliki skemanya:
             * ISPO oleh tim Sustain, skema lain oleh Admin Permohonan dan Tim
             * Teknis. Diambil dari service agar tidak menjadi salinan aturan.
             */
            $adminRole = $ownership->adminRoleFor($scheme->review_template);
            $technicalRole = $ownership->technicalRoleFor($scheme->review_template);

            $template = WorkflowTemplate::updateOrCreate(
                ['certification_scheme_id' => $scheme->id, 'version' => 1],
                ['name' => 'Workflow '.$scheme->short_name, 'is_active' => true]
            );
            $stage1Skippable = in_array($scheme->category, ['product', 'ispo'], true);
            $stage2Skippable = $scheme->category === 'product';
            $steps = [
                ['application_form', 'Application Form', 'client', 1, true, false, 14],
                ['admin_review', 'Review Admin & Tinjauan', $adminRole, 2, true, false, 5],
                ['finance', 'Invoice & Pembayaran', 'finance', 3, true, false, 7],
                ['stage_1', 'Stage 1 Audit', 'auditor', 4, ! $stage1Skippable, $stage1Skippable, 10],
                ['stage_2', 'Stage 2 Audit', 'auditor', 5, ! $stage2Skippable, $stage2Skippable, 15],
                ['qms', 'QMS/Audit Lapangan', 'auditor', 6, true, false, 15],
                ['corrective_action', 'Corrective Action', 'auditor', 7, true, false, 30],
                ['certificate_review', 'Draft Certificate Review', $technicalRole, 8, true, false, 7],
                ['final_certificate', 'Sertifikat Final', $technicalRole, 9, true, false, 7],
            ];
            foreach ($steps as [$code, $name, $role, $sort, $required, $skippable, $sla]) {
                $template->steps()->updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'role_code' => $role, 'sort_order' => $sort, 'is_required' => $required, 'is_skippable' => $skippable, 'sla_days' => $sla]
                );
            }
        }
    }
}
