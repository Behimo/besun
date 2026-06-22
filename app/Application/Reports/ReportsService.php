<?php

namespace App\Application\Reports;

use App\Infrastructure\Persistence\Eloquent\Models\Campaign;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStageTransition;
use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkReport;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportsService
{
    public function build(?Carbon $from = null, ?Carbon $to = null): array
    {
        $salesStages = $this->stages('sales');
        $marketingStages = $this->stages('marketing');

        return [
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'summary' => $this->summary($salesStages, $marketingStages),
            'sales_pipeline' => $this->salesPipeline($salesStages),
            'sales_dropoff' => $this->stageDropoff('deal', $salesStages, $from, $to),
            'marketing_funnel' => $this->marketingFunnel($marketingStages),
            'marketing_dropoff' => $this->stageDropoff('lead', $marketingStages, $from, $to),
            'campaigns' => $this->campaignPerformance(),
            'team_performance' => $this->teamPerformance($salesStages),
        ];
    }

    protected function stages(string $type): Collection
    {
        return PipelineStage::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->get();
    }

    protected function summary(Collection $salesStages, Collection $marketingStages): array
    {
        $wonStageIds = $salesStages->where('is_won', true)->pluck('id');
        $lostStageIds = $salesStages->where('is_lost', true)->pluck('id');
        $activeStageIds = $salesStages
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id');

        $totalLeads = Lead::count();
        $convertedLeads = Lead::where('status', 'converted')->count();

        return [
            'active_pipeline_value' => (float) Deal::whereIn('pipeline_stage_id', $activeStageIds)->sum('amount'),
            'won_revenue' => (float) Deal::whereIn('pipeline_stage_id', $wonStageIds)->sum('amount'),
            'lost_value' => (float) Deal::whereIn('pipeline_stage_id', $lostStageIds)->sum('amount'),
            'total_deals' => Deal::count(),
            'won_deals' => Deal::whereIn('pipeline_stage_id', $wonStageIds)->count(),
            'total_leads' => $totalLeads,
            'lead_conversion_rate' => $totalLeads > 0
                ? round(($convertedLeads / $totalLeads) * 100, 1)
                : 0,
            'active_campaigns' => Campaign::where('status', 'active')->count(),
        ];
    }

    protected function salesPipeline(Collection $stages): array
    {
        $stageIds = $stages->pluck('id');

        $aggregates = Deal::query()
            ->whereIn('pipeline_stage_id', $stageIds)
            ->select('pipeline_stage_id')
            ->selectRaw('COUNT(*) as deals_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('COALESCE(AVG(amount), 0) as avg_amount')
            ->groupBy('pipeline_stage_id')
            ->get()
            ->keyBy('pipeline_stage_id');

        return $stages->map(function (PipelineStage $stage) use ($aggregates) {
            $row = $aggregates->get($stage->id);

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
                'is_won' => (bool) $stage->is_won,
                'is_lost' => (bool) $stage->is_lost,
                'deals_count' => (int) ($row->deals_count ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
                'avg_amount' => round((float) ($row->avg_amount ?? 0), 0),
            ];
        })->values()->all();
    }

    protected function marketingFunnel(Collection $stages): array
    {
        $stageIds = $stages->pluck('id');

        $aggregates = Lead::query()
            ->where('status', '!=', 'converted')
            ->whereIn('marketing_stage_id', $stageIds)
            ->select('marketing_stage_id')
            ->selectRaw('COUNT(*) as leads_count')
            ->selectRaw('COALESCE(AVG(score), 0) as avg_score')
            ->groupBy('marketing_stage_id')
            ->get()
            ->keyBy('marketing_stage_id');

        return $stages->map(function (PipelineStage $stage) use ($aggregates) {
            $row = $aggregates->get($stage->id);

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
                'is_lost' => (bool) $stage->is_lost,
                'leads_count' => (int) ($row->leads_count ?? 0),
                'avg_score' => round((float) ($row->avg_score ?? 0), 1),
            ];
        })->values()->all();
    }

    protected function stageDropoff(
        string $entityType,
        Collection $stages,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        if ($stages->count() < 2) {
            return [];
        }

        $stageMap = $stages->keyBy('id');
        $result = [];

        foreach ($stages->values() as $index => $stage) {
            if ($index >= $stages->count() - 1) {
                continue;
            }

            $nextStage = $stages->values()[$index + 1];

            $enteredQuery = PipelineStageTransition::query()
                ->where('entity_type', $entityType)
                ->where('to_stage_id', $stage->id);

            $this->applyPeriod($enteredQuery, $from, $to);

            $entered = (clone $enteredQuery)->count();

            $progressedQuery = PipelineStageTransition::query()
                ->where('entity_type', $entityType)
                ->where('from_stage_id', $stage->id)
                ->where(function ($q) use ($nextStage, $stageMap, $stage) {
                    $q->where('to_stage_id', $nextStage->id)
                        ->orWhereIn('to_stage_id', $stageMap
                            ->filter(fn ($s) => $s->sort_order > $stage->sort_order && ! $s->is_lost)
                            ->pluck('id'));
                });

            $this->applyPeriod($progressedQuery, $from, $to);

            $progressed = (clone $progressedQuery)->count();

            $lostQuery = PipelineStageTransition::query()
                ->where('entity_type', $entityType)
                ->where('from_stage_id', $stage->id)
                ->whereIn('to_stage_id', $stageMap->where('is_lost', true)->pluck('id'));

            $this->applyPeriod($lostQuery, $from, $to);

            $lost = (clone $lostQuery)->count();

            $stillQuery = $entityType === 'deal'
                ? Deal::where('pipeline_stage_id', $stage->id)
                : Lead::where('marketing_stage_id', $stage->id)->where('status', '!=', 'converted');

            $still = $stillQuery->count();

            $dropoffRate = $entered > 0
                ? round((($entered - $progressed - $lost) / $entered) * 100, 1)
                : 0;

            $conversionRate = $entered > 0
                ? round(($progressed / $entered) * 100, 1)
                : 0;

            $result[] = [
                'from_stage' => $stage->name,
                'to_stage' => $nextStage->name,
                'from_color' => $stage->color,
                'to_color' => $nextStage->color,
                'entered' => $entered,
                'progressed' => $progressed,
                'lost' => $lost,
                'still_in_stage' => $still,
                'conversion_rate' => $conversionRate,
                'dropoff_rate' => max(0, $dropoffRate),
            ];
        }

        return $result;
    }

    protected function campaignPerformance(): array
    {
        return Campaign::query()
            ->withCount([
                'leads',
                'leads as converted_leads_count' => fn ($q) => $q->where('status', 'converted'),
            ])
            ->orderByDesc('leads_count')
            ->get()
            ->map(function (Campaign $campaign) {
                $leadsCount = (int) $campaign->leads_count;
                $converted = (int) $campaign->converted_leads_count;
                $budget = (float) ($campaign->budget ?? 0);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'channel' => $campaign->channel,
                    'status' => $campaign->status,
                    'budget' => $budget,
                    'leads_count' => $leadsCount,
                    'converted_count' => $converted,
                    'conversion_rate' => $leadsCount > 0
                        ? round(($converted / $leadsCount) * 100, 1)
                        : 0,
                    'cost_per_lead' => $leadsCount > 0 && $budget > 0
                        ? round($budget / $leadsCount, 0)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function teamPerformance(Collection $salesStages): array
    {
        $wonStageIds = $salesStages->where('is_won', true)->pluck('id');
        $lostStageIds = $salesStages->where('is_lost', true)->pluck('id');
        $activeStageIds = $salesStages
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id');

        $dealStats = Deal::query()
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as deals_count')
            ->when($wonStageIds->isNotEmpty(), fn ($q) => $q->selectRaw(
                'SUM(CASE WHEN pipeline_stage_id IN ('.$wonStageIds->implode(',').') THEN 1 ELSE 0 END) as won_count'
            ), fn ($q) => $q->selectRaw('0 as won_count'))
            ->when($lostStageIds->isNotEmpty(), fn ($q) => $q->selectRaw(
                'SUM(CASE WHEN pipeline_stage_id IN ('.$lostStageIds->implode(',').') THEN 1 ELSE 0 END) as lost_count'
            ), fn ($q) => $q->selectRaw('0 as lost_count'))
            ->when($wonStageIds->isNotEmpty(), fn ($q) => $q->selectRaw(
                'COALESCE(SUM(CASE WHEN pipeline_stage_id IN ('.$wonStageIds->implode(',').') THEN amount ELSE 0 END), 0) as won_amount'
            ), fn ($q) => $q->selectRaw('0 as won_amount'))
            ->when($activeStageIds->isNotEmpty(), fn ($q) => $q->selectRaw(
                'COALESCE(SUM(CASE WHEN pipeline_stage_id IN ('.$activeStageIds->implode(',').') THEN amount ELSE 0 END), 0) as pipeline_value'
            ), fn ($q) => $q->selectRaw('0 as pipeline_value'))
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $leadStats = Lead::query()
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as leads_count')
            ->selectRaw('SUM(CASE WHEN status = \'converted\' THEN 1 ELSE 0 END) as converted_count')
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $userIds = $dealStats->keys()
            ->merge($leadStats->keys())
            ->unique()
            ->filter();

        if ($userIds->isEmpty()) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $userIds->map(function ($userId) use ($dealStats, $leadStats, $users) {
            $deals = $dealStats->get($userId);
            $leads = $leadStats->get($userId);
            $user = $users->get($userId);

            $leadsCount = (int) ($leads->leads_count ?? 0);
            $convertedCount = (int) ($leads->converted_count ?? 0);
            $wonCount = (int) ($deals->won_count ?? 0);
            $dealsCount = (int) ($deals->deals_count ?? 0);

            return [
                'user_id' => $userId,
                'name' => $user?->name ?? 'کاربر',
                'deals_count' => $dealsCount,
                'won_deals' => $wonCount,
                'lost_deals' => (int) ($deals->lost_count ?? 0),
                'won_amount' => (float) ($deals->won_amount ?? 0),
                'pipeline_value' => (float) ($deals->pipeline_value ?? 0),
                'leads_count' => $leadsCount,
                'converted_leads' => $convertedCount,
                'lead_conversion_rate' => $leadsCount > 0
                    ? round(($convertedCount / $leadsCount) * 100, 1)
                    : 0,
                'win_rate' => $dealsCount > 0
                    ? round(($wonCount / $dealsCount) * 100, 1)
                    : 0,
            ];
        })
            ->sortByDesc('won_amount')
            ->values()
            ->all();
    }

    /** @param array<int, int>|null $userIds limit metrics to these users (null = all tenant users) */
    public function taskPerformance(?Carbon $from = null, ?Carbon $to = null, ?array $userIds = null): array
    {
        $query = Task::query()->whereNotNull('assignee_id');

        if ($userIds !== null) {
            $query->whereIn('assignee_id', $userIds);
        }

        if ($from || $to) {
            $query->where(function ($q) use ($from, $to) {
                foreach (['created_at', 'completed_at', 'due_at'] as $column) {
                    $q->orWhere(function ($sub) use ($column, $from, $to) {
                        $sub->whereNotNull($column);

                        if ($from) {
                            $sub->where($column, '>=', $from->copy()->startOfDay());
                        }

                        if ($to) {
                            $sub->where($column, '<=', $to->copy()->endOfDay());
                        }
                    });
                }
            });
        }

        $tasks = $query->get();
        $userIds = $tasks->pluck('assignee_id')->unique()->filter();

        if ($userIds->isEmpty()) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');
        $now = now();

        return $userIds->map(function ($userId) use ($tasks, $users, $now) {
            $userTasks = $tasks->where('assignee_id', $userId);
            $total = $userTasks->count();
            $completed = $userTasks->where('status', 'completed')->count();
            $pending = $userTasks->where('status', 'pending')->count();
            $inProgress = $userTasks->where('status', 'in_progress')->count();
            $overdue = $userTasks
                ->where('status', '!=', 'completed')
                ->filter(fn ($t) => $t->due_at && $t->due_at->lt($now))
                ->count();

            $completedWithDue = $userTasks->filter(
                fn ($t) => $t->status === 'completed' && $t->due_at && $t->completed_at,
            );
            $onTime = $completedWithDue->filter(
                fn ($t) => $t->completed_at->lte($t->due_at),
            )->count();

            $avgDays = $completedWithDue->isNotEmpty()
                ? round($completedWithDue->avg(
                    fn ($t) => $t->created_at->diffInDays($t->completed_at),
                ), 1)
                : 0;

            $byPriority = [
                'high' => $userTasks->where('priority', 'high')->count(),
                'medium' => $userTasks->where('priority', 'medium')->count(),
                'low' => $userTasks->where('priority', 'low')->count(),
            ];

            $user = $users->get($userId);

            return [
                'user_id' => $userId,
                'name' => $user?->name ?? 'کاربر',
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'in_progress' => $inProgress,
                'overdue' => $overdue,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'on_time_rate' => $completedWithDue->count() > 0
                    ? round(($onTime / $completedWithDue->count()) * 100, 1)
                    : 0,
                'avg_days_to_complete' => $avgDays,
                'by_priority' => $byPriority,
            ];
        })
            ->sortByDesc('completed')
            ->values()
            ->all();
    }

    /** @param array<int, int>|null $userIds limit metrics to these users (null = all tenant users) */
    public function employeePerformance(?Carbon $from = null, ?Carbon $to = null, ?array $userIds = null): array
    {
        $taskRows = collect($this->taskPerformance($from, $to, $userIds))->keyBy('user_id');
        $weights = config('hr.performance_weights');

        $taskQuery = Task::query()->whereNotNull('assignee_id');

        if ($userIds !== null) {
            $taskQuery->whereIn('assignee_id', $userIds);
        }

        $this->applyTaskPeriod($taskQuery, $from, $to);
        $tasks = $taskQuery->get();

        $reportQuery = DailyWorkReport::query()->where('status', 'submitted');

        if ($userIds !== null) {
            $reportQuery->whereIn('user_id', $userIds);
        }

        if ($from) {
            $reportQuery->where('report_date', '>=', $from->toDateString());
        }
        if ($to) {
            $reportQuery->where('report_date', '<=', $to->toDateString());
        }
        $reports = $reportQuery->with(['entries', 'reviewer'])->get();

        $userIds = $taskRows->keys()
            ->merge($tasks->pluck('assignee_id'))
            ->merge($reports->pluck('user_id'))
            ->unique()
            ->filter();

        if ($userIds->isEmpty()) {
            return [];
        }

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');
        $periodDays = max(1, ($from && $to) ? $from->diffInDays($to) + 1 : 30);

        $rows = $userIds->map(function ($userId) use (
            $taskRows,
            $tasks,
            $reports,
            $users,
            $weights,
            $periodDays,
        ) {
            $taskRow = $taskRows->get($userId, []);
            $userTasks = $tasks->where('assignee_id', $userId);
            $userReports = $reports->where('user_id', $userId);

            $taskWorkMinutes = (int) $userTasks->sum('time_spent_minutes');
            $reportMinutes = (int) $userReports->sum('total_minutes');
            $totalWorkMinutes = $taskWorkMinutes + $reportMinutes;

            $weightedTotal = (int) $userTasks->sum(fn (Task $t) => $t->effort_points ?? 3);
            $weightedCompleted = (int) $userTasks
                ->where('status', 'completed')
                ->sum(fn (Task $t) => $t->effort_points ?? 3);
            $weightedCompletionRate = $weightedTotal > 0
                ? round(($weightedCompleted / $weightedTotal) * 100, 1)
                : ($taskRow['completion_rate'] ?? 0);

            $entries = $userReports->flatMap(fn ($r) => $r->entries);
            $avgEffort = $entries->isNotEmpty()
                ? round($entries->avg('effort_score'), 1)
                : 0;

            $reviewedReports = $userReports->whereNotNull('manager_score');
            $avgManagerScore = $reviewedReports->isNotEmpty()
                ? round($reviewedReports->avg('manager_score'), 1)
                : null;

            $reportConsistency = round(min(100, ($userReports->count() / $periodDays) * 100), 1);

            $completionNotes = $userTasks
                ->where('status', 'completed')
                ->filter(fn (Task $t) => filled($t->completion_note))
                ->map(fn (Task $t) => [
                    'task_id' => $t->id,
                    'title' => $t->title,
                    'note' => $t->completion_note,
                    'completed_at' => $t->completed_at?->toDateString(),
                ])
                ->values()
                ->all();

            $dailyEntries = $userReports->flatMap(function ($report) {
                return $report->entries->map(fn ($e) => [
                    'report_date' => $report->report_date->toDateString(),
                    'title' => $e->title,
                    'description' => $e->description,
                    'minutes' => $e->minutes,
                    'effort_score' => $e->effort_score,
                    'manager_score' => $report->manager_score,
                    'manager_feedback' => $report->manager_feedback,
                ]);
            })->values()->all();

            $managerFeedbacks = $userReports
                ->filter(fn ($r) => filled($r->manager_feedback) || $r->manager_score !== null)
                ->map(fn ($r) => [
                    'report_date' => $r->report_date->toDateString(),
                    'score' => $r->manager_score,
                    'feedback' => $r->manager_feedback,
                    'reviewer' => $r->reviewer ? [
                        'id' => $r->reviewer->id,
                        'name' => $r->reviewer->name,
                    ] : null,
                    'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                ])
                ->values()
                ->all();

            return [
                'user_id' => $userId,
                'name' => $users->get($userId)?->name ?? 'کاربر',
                'total' => $taskRow['total'] ?? $userTasks->count(),
                'completed' => $taskRow['completed'] ?? $userTasks->where('status', 'completed')->count(),
                'completion_rate' => $weightedCompletionRate,
                'on_time_rate' => $taskRow['on_time_rate'] ?? 0,
                'task_work_minutes' => $taskWorkMinutes,
                'report_work_minutes' => $reportMinutes,
                'total_work_minutes' => $totalWorkMinutes,
                'daily_reports_submitted' => $userReports->count(),
                'report_consistency' => $reportConsistency,
                'avg_effort_score' => $avgEffort,
                'avg_manager_score' => $avgManagerScore,
                'reports_reviewed' => $reviewedReports->count(),
                'manager_feedbacks' => $managerFeedbacks,
                'completion_notes' => $completionNotes,
                'daily_entries' => $dailyEntries,
                'performance_score' => 0,
                'rank' => 0,
            ];
        })->values();

        $maxWorkMinutes = max(1, (int) $rows->max('total_work_minutes'));

        $rows = $rows->map(function (array $row) use ($weights, $maxWorkMinutes) {
            $workIndex = round(($row['total_work_minutes'] / $maxWorkMinutes) * 100, 1);
            $effortNorm = round((($row['avg_effort_score'] ?: 0) / 5) * 100, 1);
            $qualityNorm = round((($row['avg_manager_score'] ?? $row['avg_effort_score'] ?: 0) / 5) * 100, 1);

            $score = (
                ($weights['task_completion_rate'] / 100) * $row['completion_rate']
                + ($weights['task_on_time_rate'] / 100) * $row['on_time_rate']
                + ($weights['daily_report_consistency'] / 100) * $row['report_consistency']
                + ($weights['avg_manager_score'] / 100) * $qualityNorm
                + ($weights['avg_effort_score'] / 100) * $effortNorm
                + ($weights['work_time_index'] / 100) * $workIndex
            );

            $row['work_time_index'] = $workIndex;
            $row['performance_score'] = round(min(100, $score), 1);

            return $row;
        })
            ->sortByDesc('performance_score')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();

        return $rows;
    }

    protected function applyTaskPeriod($query, ?Carbon $from, ?Carbon $to): void
    {
        if ($from || $to) {
            $query->where(function ($q) use ($from, $to) {
                foreach (['created_at', 'completed_at', 'due_at'] as $column) {
                    $q->orWhere(function ($sub) use ($column, $from, $to) {
                        $sub->whereNotNull($column);

                        if ($from) {
                            $sub->where($column, '>=', $from->copy()->startOfDay());
                        }

                        if ($to) {
                            $sub->where($column, '<=', $to->copy()->endOfDay());
                        }
                    });
                }
            });
        }
    }

    protected function applyPeriod($query, ?Carbon $from, ?Carbon $to): void
    {
        if ($from) {
            $query->where('transitioned_at', '>=', $from->copy()->startOfDay());
        }

        if ($to) {
            $query->where('transitioned_at', '<=', $to->copy()->endOfDay());
        }
    }
}
