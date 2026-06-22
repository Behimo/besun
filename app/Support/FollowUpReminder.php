<?php

namespace App\Support;

use App\Application\Reminder\ReminderProcessor;
use Carbon\Carbon;

trait FollowUpReminder
{
    protected function applyFollowUpReminders(array $data, $existing = null): array
    {
        if (array_key_exists('next_follow_up_at', $data)) {
            if (empty($data['next_follow_up_at'])) {
                $data['follow_up_reminder_at'] = null;
                $data['follow_up_reminder_sent_at'] = null;
            } else {
                $explicit = $data['follow_up_reminder_at'] ?? null;
                $computed = ReminderProcessor::computeFollowUpReminder(
                    $data['next_follow_up_at'],
                    $explicit,
                );

                $data['follow_up_reminder_at'] = $computed?->toDateTimeString();

                $oldNext = $existing?->next_follow_up_at?->toIso8601String();
                $newNext = Carbon::parse($data['next_follow_up_at'])->toIso8601String();

                if ($newNext !== $oldNext || ($explicit && $explicit !== $existing?->follow_up_reminder_at?->toIso8601String())) {
                    $data['follow_up_reminder_sent_at'] = null;
                }
            }
        }

        return $data;
    }
}
