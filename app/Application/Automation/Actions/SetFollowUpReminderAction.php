<?php

namespace App\Application\Automation\Actions;

use App\Application\Reminder\ReminderProcessor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SetFollowUpReminderAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Model $entity, array $params): array
    {
        $offsetDays = (int) ($params['offset_days'] ?? 1);
        $offsetHours = (int) ($params['offset_hours'] ?? 0);

        $nextFollowUp = now()->addDays($offsetDays)->addHours($offsetHours);
        $reminderAt = ReminderProcessor::computeFollowUpReminder(
            $nextFollowUp->toDateTimeString(),
            null,
        );

        $entity->update([
            'next_follow_up_at' => $nextFollowUp,
            'follow_up_reminder_at' => $reminderAt,
            'follow_up_reminder_sent_at' => null,
        ]);

        return [
            'next_follow_up_at' => $nextFollowUp->toIso8601String(),
            'follow_up_reminder_at' => $reminderAt?->toIso8601String(),
        ];
    }
}
