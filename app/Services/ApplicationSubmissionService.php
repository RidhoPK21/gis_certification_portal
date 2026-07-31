<?php

namespace App\Services;

use App\Models\ApplicationValue;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\SchemeField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationSubmissionService
{
    public function __construct(
        private readonly DynamicFormService $forms,
        private readonly OrderNumberService $numbers,
        private readonly WorkflowService $workflow,
        private readonly AuditLogger $audit,
        private readonly PortalNotificationService $notifications,
    ) {
    }

    public function createDraft(int $clientId, int $schemeId, array $identity): CertificationApplication
    {
        $scheme = CertificationScheme::with(['sections.fields.options', 'requiredDocuments'])->findOrFail($schemeId);

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $clientId,
            'certification_scheme_id' => $schemeId,
            'form_version' => $identity['form_version'] ?? $scheme->form_version,
            'form_snapshot' => $this->forms->snapshot($scheme),
            'status' => 'draft',
            'company_name' => $identity['company_name'],
            'applicant_name' => $identity['applicant_name'] ?? null,
            'contact_email' => $identity['contact_email'],
            'contact_phone' => $identity['contact_phone'] ?? null,
            'created_by' => $clientId,
            'updated_by' => $clientId,
        ]);
    }

    public function saveValues(CertificationApplication $application, array $values, int $userId): void
    {
        $schema = $this->forms->schemeForApplication($application);
        $fields = $schema->sections->flatMap->fields->keyBy('code');
        $currentIds = SchemeField::whereIn('code', $fields->keys())
            ->whereHas('section', fn ($query) => $query->where('certification_scheme_id', $application->certification_scheme_id))
            ->pluck('id', 'code');

        DB::transaction(function () use ($application, $values, $userId, $fields, $currentIds) {
            foreach ($values as $code => $value) {
                $field = $fields->get($code);

                if (! $field) {
                    continue;
                }

                ApplicationValue::updateOrCreate(
                    ['application_id' => $application->id, 'field_code' => $code],
                    [
                        'scheme_field_id' => $currentIds[$code] ?? null,
                        'value_text' => is_array($value) ? null : (filled($value) ? (string) $value : null),
                        'value_json' => is_array($value) ? $value : null,
                        'field_label_snapshot' => $field->label,
                        'field_type_snapshot' => $field->type,
                        'updated_by' => $userId,
                    ]
                );
            }

            $application->update(['updated_by' => $userId]);
        });

        $application->unsetRelation('values');

        $this->audit->log('application.draft_saved', $application, [], ['fields' => array_keys($values)]);
    }

    public function submit(CertificationApplication $application, int $userId): CertificationApplication
    {
        $application->load(['scheme.sections.fields.options', 'scheme.requiredDocuments', 'documents.currentVersion', 'values', 'client']);
        $application->setRelation('scheme', $this->forms->schemeForApplication($application));
        $values = $this->forms->values($application);
        $validator = validator(
            ['fields' => $values],
            $this->forms->validationRules($application->scheme, $values, true),
            $this->forms->validationMessages(),
            $this->forms->validationAttributes($application->scheme, $values)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $missing = $this->forms->applicableDocuments($application->scheme, $values)
            ->where('requirement', 'required')
            ->reject(fn ($required) => $application->documents->firstWhere('document_code', $required->code)?->currentVersion)
            ->pluck('name')->all();

        if ($missing) {
            throw ValidationException::withMessages([
                'documents' => 'Dokumen wajib belum lengkap: ' . implode(', ', $missing),
            ]);
        }

        return DB::transaction(function () use ($application, $userId) {
            if (! $application->order_number) {
                $application->order_number = $this->numbers->generate($application);
                $application->order_date = today();
            }

            $application->submitted_at = now();
            $application->locked_at = now();
            $application->updated_by = $userId;
            $application->save();

            $this->workflow->initialize($application);

            if ($application->status === 'draft') {
                $this->workflow->transition($application, 'submitted', 'client_submit', 'Permohonan dikirim oleh klien.', $userId);
                $application = $this->workflow->transition($application->refresh(), 'admin_review', 'queue_admin_review', 'Permohonan masuk antrean review Admin.', $userId);
            } elseif ($application->status === 'revision_requested') {
                $application->revisions()->where('status', 'open')->update(['status' => 'submitted']);
                $application = $this->workflow->transition($application, 'client_revision', 'client_revision_started', 'Klien mengirim perbaikan.', $userId);
                $application = $this->workflow->transition($application->refresh(), 'admin_review', 'client_revision_submitted', 'Revisi dikirim kembali kepada Admin.', $userId);
            } elseif ($application->status === 'client_revision') {
                $application->revisions()->where('status', 'open')->update(['status' => 'submitted']);
                $application = $this->workflow->transition($application, 'admin_review', 'client_revision_submitted', 'Revisi dikirim kembali kepada Admin.', $userId);
            }

            $this->notifications->send(
                $application->client_id,
                'application_submitted',
                'Permohonan berhasil dikirim',
                'Permohonan ' . $application->order_number . ' sudah masuk ke tahap review Admin.',
                route('client.applications.show', $application)
            );
            $this->notifications->sendToRole(
                'admin_application',
                'application_received',
                'Permohonan Baru/Revisi Masuk',
                'Permohonan ' . $application->order_number . ' dari ' . $application->company_name . ' telah disubmit dan butuh review.',
                route('internal.applications.show', $application)
            );
            $this->audit->log('application.submitted', $application, [], ['order_number' => $application->order_number]);

            return $application->refresh();
        });
    }
}
