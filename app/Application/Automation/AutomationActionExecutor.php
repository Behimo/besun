<?php

namespace App\Application\Automation;

use App\Application\Automation\Actions\AssignRoundRobinAction;
use App\Application\Automation\Actions\AssignUserAction;
use App\Application\Automation\Actions\CreateTaskAction;
use App\Application\Automation\Actions\SendNotificationAction;
use App\Application\Automation\Actions\SendSmsAction;
use App\Application\Automation\Actions\SetFollowUpReminderAction;
use App\Infrastructure\Persistence\Eloquent\Models\AutomationRule;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AutomationActionExecutor
{
    public function __construct(
        protected AssignUserAction $assignUser,
        protected AssignRoundRobinAction $assignRoundRobin,
        protected SetFollowUpReminderAction $setFollowUpReminder,
        protected CreateTaskAction $createTask,
        protected SendNotificationAction $sendNotification,
        protected SendSmsAction $sendSms,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $actions, Model $entity, AutomationRule $rule, User $actor, Tenant $tenant, array $context = []): array
    {
        $results = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $params = $action['params'] ?? [];

            if (! $type) {
                continue;
            }

            $results[] = [
                'type' => $type,
                'result' => $this->runAction($type, $entity, $params, $rule, $actor, $tenant, $context),
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function runAction(
        string $type,
        Model $entity,
        array $params,
        AutomationRule $rule,
        User $actor,
        Tenant $tenant,
        array $context,
    ): array {
        return match ($type) {
            'assign_user' => $this->assignUser->execute($entity, $params),
            'assign_round_robin' => $this->assignRoundRobin->execute($entity, $params, $rule),
            'set_follow_up_reminder' => $this->setFollowUpReminder->execute($entity, $params),
            'create_task' => $this->createTask->execute($entity, $params, $actor, $context),
            'send_notification' => $this->sendNotification->execute($entity, $params, $actor, $context),
            'send_sms' => $this->sendSms->execute($entity, $params, $actor, $tenant),
            default => throw new \RuntimeException("اقدام نامعتبر: {$type}"),
        };
    }
}
