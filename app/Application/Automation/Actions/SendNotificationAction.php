<?php

namespace App\Application\Automation\Actions;

use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Models\User;
use App\Notifications\CrmReminderNotification;
use Illuminate\Database\Eloquent\Model;

class SendNotificationAction
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    public function execute(Model $entity, array $params, User $actor, array $context = []): array
    {
        $title = $params['title'] ?? 'اعلان اتوماسیون';
        $subtitle = $params['subtitle'] ?? '';
        $notify = $params['notify'] ?? 'assignee';

        $recipient = match ($notify) {
            'actor' => $actor,
            default => $entity->assignee ?? User::find($entity->getAttribute('assigned_to')),
        };

        if (! $recipient) {
            throw new \RuntimeException('گیرنده اعلان یافت نشد.');
        }

        $entityType = $entity instanceof Lead ? 'lead' : ($entity instanceof Deal ? 'deal' : 'entity');
        $url = $entity instanceof Lead ? '/apps/crm/leads' : '/apps/crm/deals';

        $recipient->notify(new CrmReminderNotification(
            title: $title,
            subtitle: $subtitle ?: ($entity->getAttribute('title') ?? $entity->getAttribute('name') ?? ''),
            url: $url,
            entityType: $entityType,
            entityId: $entity->id,
            tenantId: $entity->tenant_id,
            color: 'info',
            icon: 'tabler-robot',
        ));

        return ['notified_user_id' => $recipient->id];
    }
}
