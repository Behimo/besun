<?php

namespace App\Application\Automation;

use Illuminate\Database\Eloquent\Model;

class AutomationRuleMatcher
{
    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $context
     */
    public function matches(array $conditions, Model $entity, array $context = []): bool
    {
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $entity, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    protected function evaluateCondition(array $condition, Model $entity, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (! $field) {
            return true;
        }

        if ($field === 'assignee_is_empty') {
            $actual = $entity->getAttribute('assigned_to');

            return $operator === 'is_empty'
                ? empty($actual)
                : ! empty($actual);
        }

        if ($field === 'to_stage_id') {
            $actual = $context['to_stage_id'] ?? null;
        } else {
            $actual = $entity->getAttribute($field);
        }

        return match ($operator) {
            'equals' => (string) $actual === (string) $value,
            'not_equals' => (string) $actual !== (string) $value,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'in' => in_array((string) $actual, array_map('strval', (array) $value), true),
            'gte' => is_numeric($actual) && is_numeric($value) && (float) $actual >= (float) $value,
            'lte' => is_numeric($actual) && is_numeric($value) && (float) $actual <= (float) $value,
            default => false,
        };
    }
}
