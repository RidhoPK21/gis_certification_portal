<?php

namespace App\Services;

use App\Models\CertificationScheme;
use App\Models\SchemeField;
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

    public function visibleFields(CertificationScheme $scheme, array $values): Collection
    {
        $scheme->loadMissing('sections.fields.options');

        return $scheme->sections->flatMap->fields
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

            $rules['fields.' . $field->code] = array_values(array_unique($base));
        }

        return $rules;
    }

    public function applicableDocuments(CertificationScheme $scheme, array $values): Collection
    {
        $scheme->loadMissing('requiredDocuments');

        return $scheme->requiredDocuments
            ->filter(fn ($doc) => $doc->is_active
                && $this->conditions->passes($doc->conditional_rules, $values));
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
                    'sort_order' => $f->sort_order,
                    'is_active' => $f->is_active,
                    'options' => $f->options->map->only(['id', 'value', 'label', 'sort_order', 'is_active'])->all(),
                ])->all(),
            ])->all(),
            'documents' => $scheme->requiredDocuments->map->only([
                'id', 'code', 'name', 'description', 'requirement', 'conditional_rules',
                'allowed_extensions', 'max_size_mb', 'review_group', 'sort_order', 'is_active',
            ])->all(),
        ];
    }
}
