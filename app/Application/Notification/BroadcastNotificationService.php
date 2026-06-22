<?php

namespace App\Application\Notification;

use App\Infrastructure\Persistence\Eloquent\Models\BroadcastMessage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use App\Notifications\CrmReminderNotification;

class BroadcastNotificationService
{
    public function sendToTenant(
        Tenant $tenant,
        User $sender,
        string $title,
        string $body,
        ?string $url = null,
        string $kind = 'broadcast',
    ): BroadcastMessage {
        $recipients = $tenant->users()
            ->wherePivotNull('left_at')
            ->get();

        $record = BroadcastMessage::create([
            'tenant_id' => $tenant->id,
            'sender_id' => $sender->id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'scope' => 'tenant',
            'recipients_count' => 0,
        ]);

        $icon = $kind === 'system' ? 'tabler-info-circle' : 'tabler-speakerphone';
        $sent = 0;

        foreach ($recipients as $user) {
            $user->notify(new CrmReminderNotification(
                title: $title,
                subtitle: $body,
                url: $url ?? '/dashboards/crm',
                entityType: $kind,
                entityId: $record->id,
                tenantId: $tenant->id,
                color: $kind === 'system' ? 'info' : 'warning',
                icon: $icon,
            ));
            $sent++;
        }

        $record->update(['recipients_count' => $sent]);

        return $record->fresh(['sender:id,name']);
    }
}
