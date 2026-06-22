<?php

namespace App\Application\Automation\Actions;

use App\Infrastructure\Persistence\Eloquent\Models\AutomationRule;
use Illuminate\Database\Eloquent\Model;

class AssignRoundRobinAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Model $entity, array $params, AutomationRule $rule): array
    {
        $userIds = array_values(array_filter((array) ($params['user_ids'] ?? [])));

        if ($userIds === []) {
            throw new \RuntimeException('لیست کاربران برای تخصیص چرخشی خالی است.');
        }

        $state = $rule->runtime_state ?? [];
        $index = (int) ($state['round_robin_index'] ?? 0);
        $userId = (int) $userIds[$index % count($userIds)];

        $entity->update(['assigned_to' => $userId]);

        $rule->update([
            'runtime_state' => array_merge($state, [
                'round_robin_index' => $index + 1,
            ]),
        ]);

        return ['assigned_to' => $userId, 'round_robin_index' => $index];
    }
}
