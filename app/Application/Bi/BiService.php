<?php

namespace App\Application\Bi;

use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\CrmEntityProduct;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStageTransition;
use App\Infrastructure\Persistence\Eloquent\Models\Quote;
use App\Infrastructure\Persistence\Eloquent\Models\QuoteLineItem;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BiService
{
    public function buildDashboard(?Carbon $from = null, ?Carbon $to = null, string $granularity = 'month'): array
    {
        $to = $to?->copy()->endOfDay() ?? now()->endOfDay();
        $from = $from?->copy()->startOfDay() ?? $to->copy()->subMonths(5)->startOfMonth();
        $granularity = in_array($granularity, ['week', 'month'], true) ? $granularity : 'month';

        $salesStages = $this->salesStages();
        $wonStageIds = $salesStages->where('is_won', true)->pluck('id');
        $lostStageIds = $salesStages->where('is_lost', true)->pluck('id');
        $activeStageIds = $salesStages
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id');

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'granularity' => $granularity,
            ],
            'summary' => $this->summary($from, $to, $wonStageIds, $lostStageIds, $activeStageIds),
            'revenue_trend' => $this->revenueTrend($from, $to, $granularity, $wonStageIds, $activeStageIds),
            'deal_flow' => $this->dealFlow($from, $to, $granularity, $wonStageIds, $lostStageIds),
            'lead_sources' => $this->leadSources($from, $to),
            'quote_funnel' => $this->quoteFunnel($from, $to),
            'top_products' => $this->topProducts($from, $to),
            'top_contacts' => $this->topContacts($wonStageIds),
            'activity_breakdown' => $this->activityBreakdown($from, $to),
            'task_productivity' => $this->taskProductivity($from, $to),
            'department_kpis' => $this->departmentKpis($wonStageIds, $activeStageIds),
            'forecast' => $this->forecast($activeStageIds),
        ];
    }

    protected function salesStages(): Collection
    {
        return PipelineStage::query()
            ->where('type', 'sales')
            ->orderBy('sort_order')
            ->get();
    }

    protected function summary(
        Carbon $from,
        Carbon $to,
        Collection $wonStageIds,
        Collection $lostStageIds,
        Collection $activeStageIds,
    ): array {
        $wonRevenue = $this->wonRevenueInPeriod($from, $to, $wonStageIds);
        $periodDays = max(1, $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($periodDays - 1)->startOfDay();
        $prevRevenue = $this->wonRevenueInPeriod($prevFrom, $prevTo, $wonStageIds);

        $revenueGrowth = $prevRevenue > 0
            ? round((($wonRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($wonRevenue > 0 ? 100 : 0);

        $wonDealsQuery = Deal::query()->whereIn('pipeline_stage_id', $wonStageIds);
        $this->applyCreatedPeriod($wonDealsQuery, $from, $to);
        $wonDealsCount = (clone $wonDealsQuery)->count();
        $avgDealSize = $wonDealsCount > 0 ? round($wonRevenue / $wonDealsCount, 0) : 0;

        $quotesInPeriod = Quote::query();
        $this->applyCreatedPeriod($quotesInPeriod, $from, $to);
        $totalQuotes = (clone $quotesInPeriod)->count();
        $acceptedQuotes = (clone $quotesInPeriod)->where('status', 'accepted')->count();
        $quoteAcceptanceRate = $totalQuotes > 0
            ? round(($acceptedQuotes / $totalQuotes) * 100, 1)
            : 0;

        $avgCloseDays = $this->averageDealCloseDays($wonStageIds, $from, $to);

        $totalWorkMinutes = (int) Task::query()
            ->where('status', 'completed')
            ->whereNotNull('time_spent_minutes')
            ->where('time_spent_minutes', '>', 0)
            ->where(function (Builder $q) use ($from, $to) {
                $q->whereBetween('completed_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->sum('time_spent_minutes');

        return [
            'won_revenue' => $wonRevenue,
            'revenue_growth' => $revenueGrowth,
            'avg_deal_size' => $avgDealSize,
            'quote_acceptance_rate' => $quoteAcceptanceRate,
            'avg_close_days' => $avgCloseDays,
            'total_work_minutes' => $totalWorkMinutes,
            'active_pipeline_value' => (float) Deal::whereIn('pipeline_stage_id', $activeStageIds)->sum('amount'),
            'total_contacts' => Contact::count(),
            'total_leads' => Lead::count(),
        ];
    }

    protected function wonRevenueInPeriod(Carbon $from, Carbon $to, Collection $wonStageIds): float
    {
        if ($wonStageIds->isEmpty()) {
            return 0;
        }

        $query = Deal::query()->whereIn('pipeline_stage_id', $wonStageIds);
        $this->applyCreatedPeriod($query, $from, $to);

        return (float) $query->sum('amount');
    }

    protected function averageDealCloseDays(Collection $wonStageIds, Carbon $from, Carbon $to): float
    {
        if ($wonStageIds->isEmpty()) {
            return 0;
        }

        $wonDeals = Deal::query()
            ->whereIn('pipeline_stage_id', $wonStageIds)
            ->whereNotNull('created_at');

        $this->applyCreatedPeriod($wonDeals, $from, $to);

        $deals = $wonDeals->get(['id', 'created_at']);

        if ($deals->isEmpty()) {
            return 0;
        }

        $transitions = PipelineStageTransition::query()
            ->where('entity_type', 'deal')
            ->whereIn('entity_id', $deals->pluck('id'))
            ->whereIn('to_stage_id', $wonStageIds)
            ->orderBy('transitioned_at')
            ->get(['entity_id', 'transitioned_at'])
            ->groupBy('entity_id');

        $days = $deals->map(function (Deal $deal) use ($transitions) {
            $wonAt = $transitions->get($deal->id)?->first()?->transitioned_at;

            if (! $wonAt) {
                return null;
            }

            return $deal->created_at->diffInDays($wonAt);
        })->filter();

        return $days->isNotEmpty() ? round($days->avg(), 1) : 0;
    }

    protected function revenueTrend(
        Carbon $from,
        Carbon $to,
        string $granularity,
        Collection $wonStageIds,
        Collection $activeStageIds,
    ): array {
        $buckets = $this->buildBuckets($from, $to, $granularity);
        $format = $granularity === 'week' ? '%x-%v' : '%Y-%m';
        $driver = DB::connection()->getDriverName();

        $wonData = $this->aggregateByPeriod(
            Deal::query()->whereIn('pipeline_stage_id', $wonStageIds),
            'created_at',
            'amount',
            $from,
            $to,
            $format,
            $driver,
        );

        $pipelineData = $this->aggregateByPeriod(
            Deal::query()->whereIn('pipeline_stage_id', $activeStageIds),
            'created_at',
            'amount',
            $from,
            $to,
            $format,
            $driver,
        );

        return $buckets->map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'period_key' => $bucket['key'],
            'won_revenue' => (float) ($wonData[$bucket['key']] ?? 0),
            'pipeline_value' => (float) ($pipelineData[$bucket['key']] ?? 0),
        ])->values()->all();
    }

    protected function dealFlow(
        Carbon $from,
        Carbon $to,
        string $granularity,
        Collection $wonStageIds,
        Collection $lostStageIds,
    ): array {
        $buckets = $this->buildBuckets($from, $to, $granularity);
        $format = $granularity === 'week' ? '%x-%v' : '%Y-%m';
        $driver = DB::connection()->getDriverName();

        $created = $this->countByPeriod(Deal::query(), 'created_at', $from, $to, $format, $driver);
        $won = $this->countByPeriod(
            Deal::query()->whereIn('pipeline_stage_id', $wonStageIds),
            'created_at',
            $from,
            $to,
            $format,
            $driver,
        );
        $lost = $this->countByPeriod(
            Deal::query()->whereIn('pipeline_stage_id', $lostStageIds),
            'created_at',
            $from,
            $to,
            $format,
            $driver,
        );

        return $buckets->map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'created' => (int) ($created[$bucket['key']] ?? 0),
            'won' => (int) ($won[$bucket['key']] ?? 0),
            'lost' => (int) ($lost[$bucket['key']] ?? 0),
        ])->values()->all();
    }

    protected function leadSources(Carbon $from, Carbon $to): array
    {
        $query = Lead::query();
        $this->applyCreatedPeriod($query, $from, $to);

        return $query
            ->select('source')
            ->selectRaw('COUNT(*) as leads_count')
            ->groupBy('source')
            ->orderByDesc('leads_count')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source ?: 'نامشخص',
                'leads_count' => (int) $row->leads_count,
            ])
            ->values()
            ->all();
    }

    protected function quoteFunnel(Carbon $from, Carbon $to): array
    {
        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'cancelled'];
        $query = Quote::query();
        $this->applyCreatedPeriod($query, $from, $to);

        $counts = $query
            ->select('status')
            ->selectRaw('COUNT(*) as quotes_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $total = $counts->sum('quotes_count');

        return collect($statuses)->map(function (string $status) use ($counts, $total) {
            $row = $counts->get($status);
            $count = (int) ($row->quotes_count ?? 0);

            return [
                'status' => $status,
                'count' => $count,
                'total_amount' => (float) ($row->total_amount ?? 0),
                'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        })->values()->all();
    }

    protected function topProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $quoteProducts = QuoteLineItem::query()
            ->join('quotes', 'quotes.id', '=', 'quote_line_items.quote_id')
            ->join('products', 'products.id', '=', 'quote_line_items.product_id')
            ->whereBetween('quotes.created_at', [$from, $to])
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(quote_line_items.quantity) as quote_quantity')
            ->selectRaw('COALESCE(SUM(quote_line_items.line_total), 0) as quote_revenue')
            ->groupBy('products.id', 'products.name')
            ->get()
            ->keyBy('id');

        $linkedProducts = CrmEntityProduct::query()
            ->join('products', 'products.id', '=', 'crm_entity_products.product_id')
            ->whereBetween('crm_entity_products.created_at', [$from, $to])
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(crm_entity_products.quantity) as linked_quantity')
            ->groupBy('products.id', 'products.name')
            ->get()
            ->keyBy('id');

        $productIds = $quoteProducts->keys()->merge($linkedProducts->keys())->unique();

        return $productIds->map(function ($id) use ($quoteProducts, $linkedProducts) {
            $quote = $quoteProducts->get($id);
            $linked = $linkedProducts->get($id);

            return [
                'product_id' => $id,
                'name' => $quote?->name ?? $linked?->name ?? 'محصول',
                'quote_quantity' => (float) ($quote->quote_quantity ?? 0),
                'quote_revenue' => (float) ($quote->quote_revenue ?? 0),
                'linked_quantity' => (int) ($linked->linked_quantity ?? 0),
            ];
        })
            ->sortByDesc('quote_revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    protected function topContacts(Collection $wonStageIds, int $limit = 10): array
    {
        if ($wonStageIds->isEmpty()) {
            return [];
        }

        return Deal::query()
            ->whereIn('pipeline_stage_id', $wonStageIds)
            ->whereNotNull('contact_id')
            ->select('contact_id')
            ->selectRaw('COUNT(*) as deals_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as ltv')
            ->groupBy('contact_id')
            ->orderByDesc('ltv')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $contact = Contact::find($row->contact_id);

                return [
                    'contact_id' => $row->contact_id,
                    'name' => $contact?->name ?? 'مخاطب',
                    'company' => $contact?->company,
                    'deals_count' => (int) $row->deals_count,
                    'ltv' => (float) $row->ltv,
                ];
            })
            ->values()
            ->all();
    }

    protected function activityBreakdown(Carbon $from, Carbon $to): array
    {
        $query = Activity::query();
        $this->applyHappenedPeriod($query, $from, $to);

        return $query
            ->select('type')
            ->selectRaw('COUNT(*) as activities_count')
            ->groupBy('type')
            ->orderByDesc('activities_count')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->type,
                'count' => (int) $row->activities_count,
            ])
            ->values()
            ->all();
    }

    protected function taskProductivity(Carbon $from, Carbon $to): array
    {
        $query = Task::query()->whereNotNull('assignee_id');
        $this->applyTaskPeriod($query, $from, $to);

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
            $overdue = $userTasks
                ->where('status', '!=', 'completed')
                ->filter(fn (Task $t) => $t->due_at && $t->due_at->lt($now))
                ->count();
            $workMinutes = (int) $userTasks->sum('time_spent_minutes');

            return [
                'user_id' => $userId,
                'name' => $users->get($userId)?->name ?? 'کاربر',
                'total' => $total,
                'completed' => $completed,
                'overdue' => $overdue,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'work_minutes' => $workMinutes,
            ];
        })
            ->sortByDesc('completed')
            ->values()
            ->all();
    }

    protected function departmentKpis(Collection $wonStageIds, Collection $activeStageIds): array
    {
        $departments = ['sales', 'marketing', 'finance'];

        return collect($departments)->map(function (string $department) use ($wonStageIds, $activeStageIds) {
            $dealsQuery = Deal::query()->where('department', $department);
            $leadsCount = Lead::query()->where('department', $department)->count();

            return [
                'department' => $department,
                'deals_count' => (clone $dealsQuery)->count(),
                'won_revenue' => $wonStageIds->isNotEmpty()
                    ? (float) (clone $dealsQuery)->whereIn('pipeline_stage_id', $wonStageIds)->sum('amount')
                    : 0,
                'pipeline_value' => $activeStageIds->isNotEmpty()
                    ? (float) (clone $dealsQuery)->whereIn('pipeline_stage_id', $activeStageIds)->sum('amount')
                    : 0,
                'leads_count' => $leadsCount,
            ];
        })->values()->all();
    }

    protected function forecast(Collection $activeStageIds): array
    {
        if ($activeStageIds->isEmpty()) {
            return [];
        }

        $start = now()->startOfMonth();
        $end = now()->addMonths(3)->endOfMonth();

        $deals = Deal::query()
            ->whereIn('pipeline_stage_id', $activeStageIds)
            ->whereNotNull('expected_close_date')
            ->whereBetween('expected_close_date', [$start->toDateString(), $end->toDateString()])
            ->get(['expected_close_date', 'amount']);

        $months = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $months->put($key, [
                'month' => $key,
                'label' => $cursor->format('Y/m'),
                'deals_count' => 0,
                'total_amount' => 0.0,
            ]);
            $cursor->addMonth();
        }

        foreach ($deals as $deal) {
            $key = $deal->expected_close_date->format('Y-m');
            if (! $months->has($key)) {
                continue;
            }
            $row = $months->get($key);
            $row['deals_count']++;
            $row['total_amount'] += (float) $deal->amount;
            $months->put($key, $row);
        }

        return $months->values()->all();
    }

    protected function buildBuckets(Carbon $from, Carbon $to, string $granularity): Collection
    {
        $buckets = collect();
        $cursor = $from->copy()->startOfDay();

        if ($granularity === 'week') {
            $cursor->startOfWeek();
        } else {
            $cursor->startOfMonth();
        }

        while ($cursor->lte($to)) {
            if ($granularity === 'week') {
                $key = sprintf('%d-%02d', $cursor->isoWeekYear(), $cursor->isoWeek());
                $label = 'هفته '.$cursor->isoWeek().' '.$cursor->isoWeekYear();
                $cursor->addWeek();
            } else {
                $key = $cursor->format('Y-m');
                $label = $cursor->format('Y/m');
                $cursor->addMonth();
            }

            $buckets->push(['key' => $key, 'label' => $label]);
        }

        return $buckets;
    }

    protected function periodExpression(string $column, string $format, string $driver): string
    {
        if ($driver === 'sqlite') {
            return "strftime('{$this->sqliteFormat($format)}', {$column})";
        }

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    protected function sqliteFormat(string $format): string
    {
        return match ($format) {
            '%Y-%m' => '%Y-%m',
            '%x-W%v' => '%Y-W%W',
            default => '%Y-%m',
        };
    }

    protected function aggregateByPeriod(
        Builder $query,
        string $column,
        string $sumColumn,
        Carbon $from,
        Carbon $to,
        string $format,
        string $driver,
    ): array {
        $periodExpr = $this->periodExpression($column, $format, $driver);

        return (clone $query)
            ->whereBetween($column, [$from, $to])
            ->selectRaw("{$periodExpr} as period_key")
            ->selectRaw("COALESCE(SUM({$sumColumn}), 0) as total")
            ->groupBy('period_key')
            ->pluck('total', 'period_key')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    protected function countByPeriod(
        Builder $query,
        string $column,
        Carbon $from,
        Carbon $to,
        string $format,
        string $driver,
    ): array {
        $periodExpr = $this->periodExpression($column, $format, $driver);

        return (clone $query)
            ->whereBetween($column, [$from, $to])
            ->selectRaw("{$periodExpr} as period_key")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('period_key')
            ->pluck('total', 'period_key')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    protected function applyCreatedPeriod(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    protected function applyHappenedPeriod(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where(function (Builder $q) use ($from, $to) {
            $q->whereBetween('happened_at', [$from, $to])
                ->orWhereBetween('created_at', [$from, $to]);
        });
    }

    protected function applyTaskPeriod(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where(function (Builder $q) use ($from, $to) {
            foreach (['created_at', 'completed_at', 'due_at'] as $column) {
                $q->orWhereBetween($column, [$from, $to]);
            }
        });
    }

    public function applyDealFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->where('assigned_to', $filters['assignee_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    public function applyLeadFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->where('assigned_to', $filters['assignee_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }
}
