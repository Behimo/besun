<?php

namespace App\Application\Calendar;

use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use Carbon\Carbon;

class CalendarService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function events(User $user, Carbon $from, Carbon $to): array
    {
        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());
        $isManager = $tenant->isManagerOrOwner($user);

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $events = collect();

        $events = $events->concat($this->taskDueEvents($user, $from, $to, $isManager));
        $events = $events->concat($this->taskReminderEvents($user, $from, $to, $isManager));
        $events = $events->concat($this->activityEvents($user, $from, $to, $isManager));
        $events = $events->concat($this->leadFollowUpEvents($user, $from, $to, $isManager));
        $events = $events->concat($this->dealFollowUpEvents($user, $from, $to, $isManager));

        return $events->values()->all();
    }

    protected function taskDueEvents(User $user, Carbon $from, Carbon $to, bool $isManager)
    {
        $query = Task::with('assignee')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to]);

        if (! $isManager) {
            $query->where(function ($q) use ($user) {
                $q->where('assignee_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        return $query->get()->map(function (Task $task) {
            $assignee = $task->assignee?->name;

            return [
                'id' => 'task-due-'.$task->id,
                'title' => 'موعد: '.$task->title.($assignee ? " — {$assignee}" : ''),
                'start' => $task->due_at->toIso8601String(),
                'end' => $task->due_at->copy()->addHour()->toIso8601String(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'task',
                    'task_id' => $task->id,
                    'calendar' => $this->taskCalendarCategory($task),
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'assignee' => $assignee,
                ],
            ];
        });
    }

    protected function taskReminderEvents(User $user, Carbon $from, Carbon $to, bool $isManager)
    {
        $query = Task::with('assignee')
            ->whereNotNull('reminder_at')
            ->whereBetween('reminder_at', [$from, $to])
            ->where('status', '!=', 'completed');

        if (! $isManager) {
            $query->where(function ($q) use ($user) {
                $q->where('assignee_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        return $query->get()->map(function (Task $task) {
            return [
                'id' => 'task-reminder-'.$task->id,
                'title' => 'یادآوری: '.$task->title,
                'start' => $task->reminder_at->toIso8601String(),
                'end' => $task->reminder_at->copy()->addMinutes(30)->toIso8601String(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'task_reminder',
                    'task_id' => $task->id,
                    'calendar' => 'TaskReminder',
                ],
            ];
        });
    }

    protected function activityEvents(User $user, Carbon $from, Carbon $to, bool $isManager)
    {
        $query = Activity::with('user')
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('happened_at')
                        ->whereBetween('happened_at', [$from, $to]);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('scheduled_at')
                        ->whereBetween('scheduled_at', [$from, $to]);
                });
            });

        if (! $isManager) {
            $query->where('user_id', $user->id);
        }

        return $query->get()->map(function (Activity $activity) {
            $at = $activity->scheduled_at ?? $activity->happened_at;
            $typeLabel = match ($activity->type) {
                'call' => 'تماس',
                'meeting' => 'جلسه',
                default => 'یادداشت',
            };
            $prefix = $activity->scheduled_at && ! $activity->happened_at ? 'برنامه‌ریزی' : '';

            return [
                'id' => 'activity-'.$activity->id,
                'title' => trim("{$prefix} {$typeLabel}: ".($activity->subject ?: 'بدون عنوان')),
                'start' => $at->toIso8601String(),
                'end' => $at->copy()->addHour()->toIso8601String(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'activity',
                    'activity_id' => $activity->id,
                    'calendar' => $this->activityCalendarCategory($activity),
                    'activity_type' => $activity->type,
                    'user' => $activity->user?->name,
                ],
            ];
        });
    }

    protected function leadFollowUpEvents(User $user, Carbon $from, Carbon $to, bool $isManager)
    {
        $query = Lead::query()
            ->whereNotNull('next_follow_up_at')
            ->whereBetween('next_follow_up_at', [$from, $to])
            ->where('status', '!=', 'converted');

        if (! $isManager) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get()->map(fn (Lead $lead) => [
            'id' => 'lead-follow-'.$lead->id,
            'title' => 'پیگیری لید: '.$lead->name,
            'start' => $lead->next_follow_up_at->toIso8601String(),
            'end' => $lead->next_follow_up_at->copy()->addHour()->toIso8601String(),
            'allDay' => false,
            'extendedProps' => [
                'type' => 'lead_follow_up',
                'lead_id' => $lead->id,
                'calendar' => 'LeadFollowUp',
            ],
        ]);
    }

    protected function dealFollowUpEvents(User $user, Carbon $from, Carbon $to, bool $isManager)
    {
        $query = Deal::query()
            ->whereNotNull('next_follow_up_at')
            ->whereBetween('next_follow_up_at', [$from, $to]);

        if (! $isManager) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get()->map(fn (Deal $deal) => [
            'id' => 'deal-follow-'.$deal->id,
            'title' => 'پیگیری معامله: '.$deal->title,
            'start' => $deal->next_follow_up_at->toIso8601String(),
            'end' => $deal->next_follow_up_at->copy()->addHour()->toIso8601String(),
            'allDay' => false,
            'extendedProps' => [
                'type' => 'deal_follow_up',
                'deal_id' => $deal->id,
                'calendar' => 'DealFollowUp',
            ],
        ]);
    }

    protected function taskCalendarCategory(Task $task): string
    {
        if ($task->status === 'completed') {
            return 'Holiday';
        }

        return match ($task->priority) {
            'high' => 'Personal',
            'low' => 'ETC',
            default => 'Family',
        };
    }

    protected function activityCalendarCategory(Activity $activity): string
    {
        return match ($activity->type) {
            'call' => 'Business',
            default => 'ETC',
        };
    }
}
