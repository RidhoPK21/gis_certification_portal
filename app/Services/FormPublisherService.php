<?php

namespace App\Services;

use App\Models\CertificationScheme;
use App\Models\FormConfigurationVersion;
use Illuminate\Support\Facades\DB;

class FormPublisherService
{
    public function __construct(
        private readonly DynamicFormService $forms,
        private readonly AuditLogger $audit
    ) {
    }

    public function publish(
        CertificationScheme $scheme,
        ?int $userId = null,
        string $reason = 'Form configuration updated'
    ): FormConfigurationVersion {
        return DB::transaction(function () use ($scheme, $userId, $reason) {
            $scheme->refresh();
            $next = ((int) $scheme->form_version) + 1;
            $scheme->update(['form_version' => $next]);
            $scheme->unsetRelation('sections')->unsetRelation('requiredDocuments');

            $snapshot = $this->forms->snapshot($scheme);

            $version = FormConfigurationVersion::create([
                'certification_scheme_id' => $scheme->id,
                'version' => $next,
                'snapshot' => $snapshot,
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            $this->audit->log(
                'form_configuration.published',
                $scheme,
                ['version' => $next - 1],
                ['version' => $next, 'reason' => $reason]
            );

            return $version;
        });
    }
}
