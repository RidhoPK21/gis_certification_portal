<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\SchemeField;
use App\Models\SchemeFieldOption;
use App\Models\SchemeRequiredDocument;
use App\Models\SchemeSection;
use Illuminate\Support\Collection;

class DynamicFormService
{
    public function __construct(
        private readonly ConditionalRuleEvaluator $conditions
    ) {
    }

    public function catalog(CertificationScheme $scheme): CertificationScheme
    {
        return $scheme->load(['sections.fields.options', 'requiredDocuments']);
    }

    /**
     * Merekonstruksi skema dari snapshot form yang tersimpan pada permohonan,
     * sehingga permohonan lama tetap memakai struktur form saat draft dibuat.
     */
    public function schemeForApplication(CertificationApplication $application): CertificationScheme
    {
        $snapshot = $application->form_snapshot;

        if (! $snapshot || empty($snapshot['scheme'])) {
            return $this->catalog($application->scheme);
        }

        $schemeData = $snapshot['scheme'];

        $scheme = new CertificationScheme($schemeData);
        $scheme->id = $schemeData['id'] ?? $application->certification_scheme_id;

        $sections = collect($snapshot['sections'] ?? [])
            ->map(function (array $sectionData) {
                $fieldRows = $sectionData['fields'] ?? [];
                unset($sectionData['fields']);

                $section = new SchemeSection($sectionData);

                $fields = collect($fieldRows)->map(function (array $fieldData) {
                    $optionRows = $fieldData['options'] ?? [];
                    unset($fieldData['options']);

                    $field = new SchemeField($fieldData);

                    $options = collect($optionRows)->map(
                        fn (array $option) => new SchemeFieldOption($option)
                    );

                    $field->setRelation('options', $options);

                    return $field;
                });

                $section->setRelation('fields', $fields);

                return $section;
            });

        $documents = collect($snapshot['documents'] ?? [])
            ->map(fn (array $document) => new SchemeRequiredDocument($document));

        $scheme->setRelation('sections', $sections);
        $scheme->setRelation('requiredDocuments', $documents);

        return $scheme;
    }

    public function values(CertificationApplication $application): array
    {
        return $application->values()->get()->mapWithKeys(fn ($row) => [
            $row->field_code => $row->value_json ?? $row->value_text,
        ])->all();
    }

    /**
     * Bagian form yang berlaku untuk isian saat ini.
     *
     * Form Aplikasi ISPO memuat bagian yang hanya relevan bagi ruang lingkup
     * tertentu (G Pekebun, H Perusahaan Perkebunan, I Industri Hilir,
     * J Bioenergi); bagian yang tidak dipilih tidak ditampilkan sama sekali,
     * bukan sekadar dikosongkan isinya.
     *
     * @return Collection<int, SchemeSection>
     */
    public function visibleSections(CertificationScheme $scheme, array $values): Collection
    {
        $scheme->loadMissing('sections.fields.options');

        return $scheme->sections
            ->filter(fn (SchemeSection $section) => $this->conditions->passes($section->conditional_rules, $values))
            ->values();
    }

    public function visibleFields(CertificationScheme $scheme, array $values): Collection
    {
        // Field pada bagian yang tersembunyi ikut tersembunyi, sehingga tidak
        // divalidasi maupun diminta pada tahap submit.
        return $this->visibleSections($scheme, $values)
            ->flatMap->fields
            ->filter(fn (SchemeField $field) => $field->is_active
                && $this->conditions->passes($field->conditional_rules, $values));
    }

    public function validationRules(CertificationScheme $scheme, array $values, bool $forSubmit = false): array
    {
        $rules = [];

        foreach ($this->visibleFields($scheme, $values) as $field) {
            $base = $field->validation_rules ?: [];

            if ($forSubmit && $field->is_required) {
                array_unshift($base, 'required');
            } else {
                array_unshift($base, 'nullable');
            }

            if (in_array($field->type, ['number', 'currency'], true)) {
                $base[] = 'numeric';
            }
            if ($field->type === 'email') {
                $base[] = 'email:rfc';
            }
            if ($field->type === 'date') {
                $base[] = 'date';
            }
            if ($field->type === 'url') {
                $base[] = 'url';
            }
            if (in_array($field->type, ['checkbox_group', 'multiselect'], true)) {
                $base[] = 'array';
            }
            if ($field->type === 'file' && $forSubmit && $field->is_required) {
                $base[] = function (string $attribute, mixed $value, \Closure $fail) {
                    if (blank($value)) {
                        $fail('Kolom :attribute wajib diunggah.');
                        return;
                    }
                    if ($value instanceof \Illuminate\Http\UploadedFile && ! $value->isValid()) {
                        $fail('Kolom :attribute gagal diunggah.');
                        return;
                    }
                    if (is_array($value) && empty($value['path']) && empty($value['original_name']) && empty($value['name']) && empty($value['filename'])) {
                        $fail('Kolom :attribute belum memiliki file yang tersimpan.');
                        return;
                    }
                };
            }

            $uniqueRules = [];
            foreach ($base as $rule) {
                if (! in_array($rule, $uniqueRules, true)) {
                    $uniqueRules[] = $rule;
                }
            }
            $rules['fields.' . $field->code] = $uniqueRules;
        }

        return $rules;
    }

    public function validationAttributes(CertificationScheme $scheme, array $values): array
    {
        $attributes = [];

        foreach ($this->visibleFields($scheme, $values) as $field) {
            $attributes['fields.' . $field->code] = $field->label;
        }

        return $attributes;
    }

    public function validationMessages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'email' => 'Kolom :attribute harus berupa format email yang valid.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'url' => 'Kolom :attribute harus berupa URL yang valid.',
            'array' => 'Kolom :attribute format tidak valid / belum lengkap.',
        ];
    }

    public function applicableDocuments(CertificationScheme $scheme, array $values): Collection
    {
        $scheme->loadMissing('requiredDocuments');

        return $scheme->requiredDocuments
            ->filter(fn ($doc) => $doc->is_active
                && $this->conditions->passes($doc->conditional_rules, $values));
    }

    public function completion(CertificationApplication $application): int
    {
        $values = $this->values($application);
        $scheme = $this->schemeForApplication($application);
        $requiredFields = $this->visibleFields($scheme, $values)->where('is_required', true);
        $fieldTotal = $requiredFields->count();
        $fieldDone = $requiredFields->filter(fn ($field) => $this->isFieldFilled($field, data_get($values, $field->code)))->count();
        $docs = $this->applicableDocuments($scheme, $values)->where('requirement', 'required');
        $uploaded = $application->documents()
            ->whereIn('document_code', $docs->pluck('code'))
            ->whereHas('currentVersion')
            ->count();
        $total = $fieldTotal + $docs->count();

        return $total === 0 ? 100 : (int) floor((($fieldDone + $uploaded) / $total) * 100);
    }

    public function isFieldFilled(SchemeField $field, mixed $value): bool
    {
        if (blank($value)) {
            return false;
        }

        if ($field->type === 'file') {
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                return $value->isValid();
            }

            if (is_array($value)) {
                return ! empty($value['path']) || ! empty($value['original_name']) || ! empty($value['name']) || ! empty($value['filename']);
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return ! empty($decoded['path']) || ! empty($decoded['original_name']) || ! empty($decoded['name']) || ! empty($decoded['filename']);
                }

                return trim($value) !== '';
            }

            return false;
        }

        return filled($value);
    }

    public function snapshot(CertificationScheme $scheme): array
    {
        $this->catalog($scheme);

        return [
            'scheme' => $scheme->only([
                'id', 'code', 'slug', 'name', 'short_name', 'category', 'standard',
                'description', 'form_version', 'order_prefix', 'review_template',
                'is_active', 'sort_order',
            ]),
            'sections' => $scheme->sections->map(fn ($section) => [
                'id' => $section->id,
                'code' => $section->code,
                'title' => $section->title,
                'description' => $section->description,
                'icon' => $section->icon,
                'conditional_rules' => $section->conditional_rules,
                'sort_order' => $section->sort_order,
                'fields' => $section->fields->map(fn ($f) => [
                    'id' => $f->id,
                    'code' => $f->code,
                    'label' => $f->label,
                    'type' => $f->type,
                    'placeholder' => $f->placeholder,
                    'help_text' => $f->help_text,
                    'unit' => $f->unit,
                    'is_required' => $f->is_required,
                    'is_repeatable' => $f->is_repeatable,
                    'validation_rules' => $f->validation_rules,
                    'conditional_rules' => $f->conditional_rules,
                    'column_definitions' => $f->column_definitions,
                    'row_definitions' => $f->row_definitions,
                    'sort_order' => $f->sort_order,
                    'is_active' => $f->is_active,
                    'options' => $f->options->map->only(['id', 'value', 'label', 'sort_order', 'is_active'])->all(),
                ])->all(),
            ])->all(),
            'documents' => $scheme->requiredDocuments->map->only([
                'id', 'code', 'name', 'description', 'requirement', 'document_group',
                'conditional_rules', 'allowed_extensions', 'max_size_mb', 'review_group',
                'sort_order', 'is_active',
            ])->all(),
        ];
    }
}
