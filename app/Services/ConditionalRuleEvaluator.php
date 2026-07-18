<?php

namespace App\Services;

class ConditionalRuleEvaluator
{
    public function passes(?array $rule, array $values): bool
    {
        if (! $rule) {
            return true;
        }

        if (isset($rule['all'])) {
            return collect($rule['all'])->every(fn ($item) => $this->passes($item, $values));
        }

        if (isset($rule['any'])) {
            return collect($rule['any'])->contains(fn ($item) => $this->passes($item, $values));
        }

        $actual = data_get($values, $rule['field'] ?? '');
        $expected = $rule['value'] ?? null;

        return match ($rule['operator'] ?? 'equals') {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'in' => in_array($actual, (array) $expected, true),
            'not_in' => ! in_array($actual, (array) $expected, true),
            'truthy' => ! empty($actual),
            'falsy' => empty($actual),
            'contains' => is_array($actual)
                ? in_array($expected, $actual, true)
                : str_contains((string) $actual, (string) $expected),
            default => true,
        };
    }
}
