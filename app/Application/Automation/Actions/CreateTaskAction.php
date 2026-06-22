<?php

namespace App\Application\Automation\Actions;

use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CreateTaskAction
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    public function execute(Model $entity, array $params, User $actor, array $context = []): array
    {
        $title = $params['title'] ?? null;

        if (! $title) {
            throw new \RuntimeException('عنوان تسک مشخص نشده است.');
        }

        $assigneeId = $params['assignee_id'] ?? $entity->getAttribute('assigned_to') ?? $actor->id;
        $dueOffsetDays = (int) ($params['due_offset_days'] ?? 1);
        $dueAt = Carbon::now()->addDays($dueOffsetDays);

        $relatedType = match (true) {
            $entity instanceof Lead => Lead::class,
            $entity instanceof Deal => Deal::class,
            default => null,
        };

        $task = Task::create([
            'tenant_id' => $entity->tenant_id,
            'workspace_id' => $entity->workspace_id,
            'title' => $title,
            'description' => $params['description'] ?? null,
            'assignee_id' => $assigneeId,
            'created_by' => $actor->id,
            'assigned_by' => $actor->id,
            'due_at' => $dueAt,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $entity->id,
        ]);

        return ['task_id' => $task->id, 'assignee_id' => (int) $assigneeId];
    }
}
