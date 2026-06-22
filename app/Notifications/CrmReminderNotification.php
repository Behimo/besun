<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CrmReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $subtitle,
        public string $url,
        public string $entityType,
        public int $entityId,
        public ?int $tenantId = null,
        public string $color = 'primary',
        public ?string $icon = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'tenant_id' => $this->tenantId,
            'color' => $this->color,
            'icon' => $this->icon,
        ];
    }
}
