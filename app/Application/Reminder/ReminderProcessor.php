<?php

namespace App\Application\Reminder;

use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Models\User;
use App\Notifications\CrmReminderNotification;
use Carbon\Carbon;

class ReminderProcessor
{
    public function process(): int
    {
        $count = 0;
        $count += $this->processTasks();
        $count += $this->processLeads();
        $count += $this->processDeals();
        $count += $this->processActivities();

        return $count;
    }

    protected function processTasks(): int
    {
        $count = 0;

        Task::query()
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->where('status', '!=', 'completed')
            ->with('assignee')
            ->chunkById(50, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    $user = $task->assignee ?? User::find($task->created_by);

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new CrmReminderNotification(
                        title: 'یادآوری تسک',
                        subtitle: $task->title,
                        url: '/apps/crm/tasks',
                        entityType: 'task',
                        entityId: $task->id,
                        tenantId: $task->tenant_id,
                        color: 'error',
                        icon: 'tabler-checkbox',
                    ));

                    $task->update(['reminder_sent_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    protected function processLeads(): int
    {
        $count = 0;

        Lead::query()
            ->whereNotNull('follow_up_reminder_at')
            ->where('follow_up_reminder_at', '<=', now())
            ->whereNull('follow_up_reminder_sent_at')
            ->where('status', '!=', 'converted')
            ->with('assignee')
            ->chunkById(50, function ($leads) use (&$count) {
                foreach ($leads as $lead) {
                    $user = $lead->assignee;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new CrmReminderNotification(
                        title: 'پیگیری لید',
                        subtitle: $lead->name,
                        url: '/apps/crm/leads',
                        entityType: 'lead',
                        entityId: $lead->id,
                        tenantId: $lead->tenant_id,
                        color: 'warning',
                        icon: 'tabler-user-search',
                    ));

                    $lead->update(['follow_up_reminder_sent_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    protected function processDeals(): int
    {
        $count = 0;

        Deal::query()
            ->whereNotNull('follow_up_reminder_at')
            ->where('follow_up_reminder_at', '<=', now())
            ->whereNull('follow_up_reminder_sent_at')
            ->chunkById(50, function ($deals) use (&$count) {
                foreach ($deals as $deal) {
                    $user = User::find($deal->assigned_to);

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new CrmReminderNotification(
                        title: 'پیگیری معامله',
                        subtitle: $deal->title,
                        url: '/apps/crm/deals',
                        entityType: 'deal',
                        entityId: $deal->id,
                        tenantId: $deal->tenant_id,
                        color: 'primary',
                        icon: 'tabler-chart-funnel',
                    ));

                    $deal->update(['follow_up_reminder_sent_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    protected function processActivities(): int
    {
        $count = 0;

        Activity::query()
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->whereNull('happened_at')
            ->with('user')
            ->chunkById(50, function ($activities) use (&$count) {
                foreach ($activities as $activity) {
                    $user = $activity->user;

                    if (! $user) {
                        continue;
                    }

                    $typeLabel = match ($activity->type) {
                        'call' => 'تماس',
                        'meeting' => 'جلسه',
                        default => 'فعالیت',
                    };

                    $user->notify(new CrmReminderNotification(
                        title: "یادآوری {$typeLabel}",
                        subtitle: $activity->subject ?: 'بدون عنوان',
                        url: '/apps/crm/activities',
                        entityType: 'activity',
                        entityId: $activity->id,
                        tenantId: $activity->tenant_id,
                        color: 'info',
                        icon: 'tabler-calendar-event',
                    ));

                    $activity->update(['reminder_sent_at' => now()]);
                    $count++;
                }
            });

        return $count;
    }

    public static function computeFollowUpReminder(?string $nextFollowUpAt, ?string $explicitReminderAt = null): ?Carbon
    {
        if ($explicitReminderAt) {
            return Carbon::parse($explicitReminderAt);
        }

        if (! $nextFollowUpAt) {
            return null;
        }

        $followUp = Carbon::parse($nextFollowUpAt);

        return $followUp->copy()->subHour();
    }

    public static function computeActivityReminder(?string $scheduledAt, ?string $explicitReminderAt = null): ?Carbon
    {
        if ($explicitReminderAt) {
            return Carbon::parse($explicitReminderAt);
        }

        if (! $scheduledAt) {
            return null;
        }

        $scheduled = Carbon::parse($scheduledAt);

        if ($scheduled->isPast()) {
            return null;
        }

        return $scheduled->copy()->subMinutes(15);
    }
}
