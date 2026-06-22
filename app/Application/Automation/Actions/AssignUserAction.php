<?php

namespace App\Application\Automation\Actions;

use Illuminate\Database\Eloquent\Model;

class AssignUserAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Model $entity, array $params): array
    {
        $userId = $params['user_id'] ?? null;

        if (! $userId) {
            throw new \RuntimeException('کاربر برای تخصیص مشخص نشده است.');
        }

        $entity->update(['assigned_to' => $userId]);

        return ['assigned_to' => (int) $userId];
    }
}
