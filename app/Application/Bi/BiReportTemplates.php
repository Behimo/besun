<?php

namespace App\Application\Bi;

use App\Application\Reports\ReportsService;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Quote;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use Carbon\Carbon;

class BiReportTemplates
{
    public function __construct(
        protected BiService $bi,
        protected ReportsService $reports,
    ) {}

    /** @return array<int, array{slug: string, title: string, description: string, filters: array<string>}> */
    public function list(): array
    {
        return [
            [
                'slug' => 'sales_performance',
                'title' => 'عملکرد فروش',
                'description' => 'ارزش قیف، عملکرد تیم و روند درآمد',
                'filters' => ['from', 'to', 'department', 'assignee_id'],
            ],
            [
                'slug' => 'marketing_roi',
                'title' => 'بازگشت بازاریابی',
                'description' => 'کمپین‌ها، منبع لید و قیف بازاریابی',
                'filters' => ['from', 'to', 'department', 'assignee_id'],
            ],
            [
                'slug' => 'customer_insights',
                'title' => 'بینش مشتری',
                'description' => 'ارزش طول عمر مشتری و مخاطبین پرارزش',
                'filters' => ['from', 'to', 'department'],
            ],
            [
                'slug' => 'operations',
                'title' => 'بهره‌وری عملیات',
                'description' => 'تسک‌ها، فعالیت‌ها و زمان کار',
                'filters' => ['from', 'to', 'assignee_id'],
            ],
            [
                'slug' => 'products_quotes',
                'title' => 'محصول و پیش‌فاکتور',
                'description' => 'محصولات پرتقاضا و قیف پیش‌فاکتور',
                'filters' => ['from', 'to', 'department'],
            ],
        ];
    }

    public function build(string $template, array $filters = []): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to']) : null;

        return match ($template) {
            'sales_performance' => $this->salesPerformance($from, $to, $filters),
            'marketing_roi' => $this->marketingRoi($from, $to, $filters),
            'customer_insights' => $this->customerInsights($from, $to, $filters),
            'operations' => $this->operations($from, $to, $filters),
            'products_quotes' => $this->productsQuotes($from, $to, $filters),
            default => abort(422, 'قالب گزارش نامعتبر است.'),
        };
    }

    protected function salesPerformance(?Carbon $from, ?Carbon $to, array $filters): array
    {
        $salesStages = PipelineStage::query()->where('type', 'sales')->orderBy('sort_order')->get();
        $wonStageIds = $salesStages->where('is_won', true)->pluck('id');
        $activeStageIds = $salesStages->where('is_won', false)->where('is_lost', false)->pluck('id');

        $from = $from ?? now()->subMonths(3)->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $dealQuery = Deal::query();
        $this->bi->applyDealFilters($dealQuery, array_merge($filters, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        $wonRevenue = $wonStageIds->isNotEmpty()
            ? (float) (clone $dealQuery)->whereIn('pipeline_stage_id', $wonStageIds)->sum('amount')
            : 0;

        $pipelineValue = $activeStageIds->isNotEmpty()
            ? (float) Deal::query()
                ->when(! empty($filters['department']), fn ($q) => $q->where('department', $filters['department']))
                ->when(! empty($filters['assignee_id']), fn ($q) => $q->where('assigned_to', $filters['assignee_id']))
                ->whereIn('pipeline_stage_id', $activeStageIds)
                ->sum('amount')
            : 0;

        $reports = $this->reports->build($from, $to);

        return [
            'template' => 'sales_performance',
            'title' => 'عملکرد فروش',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'won_revenue' => $wonRevenue,
                'pipeline_value' => $pipelineValue,
                'total_deals' => (clone $dealQuery)->count(),
            ],
            'sales_pipeline' => $reports['sales_pipeline'],
            'team_performance' => $this->filterTeamPerformance($reports['team_performance'], $filters),
            'revenue_trend' => $this->bi->buildDashboard($from, $to, 'month')['revenue_trend'],
            'tables' => [
                [
                    'key' => 'team_performance',
                    'title' => 'عملکرد تیم فروش',
                    'columns' => [
                        ['key' => 'name', 'title' => 'نام'],
                        ['key' => 'won_amount', 'title' => 'درآمد برنده'],
                        ['key' => 'won_deals', 'title' => 'معاملات برنده'],
                        ['key' => 'pipeline_value', 'title' => 'ارزش قیف'],
                        ['key' => 'win_rate', 'title' => 'نرخ برد (٪)'],
                    ],
                    'rows' => $this->filterTeamPerformance($reports['team_performance'], $filters),
                ],
            ],
        ];
    }

    protected function marketingRoi(?Carbon $from, ?Carbon $to, array $filters): array
    {
        $from = $from ?? now()->subMonths(3)->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $reports = $this->reports->build($from, $to);

        $leadQuery = Lead::query();
        $this->bi->applyLeadFilters($leadQuery, array_merge($filters, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        return [
            'template' => 'marketing_roi',
            'title' => 'بازگشت بازاریابی',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_leads' => (clone $leadQuery)->count(),
                'converted_leads' => (clone $leadQuery)->where('status', 'converted')->count(),
                'active_campaigns' => $reports['summary']['active_campaigns'],
            ],
            'marketing_funnel' => $reports['marketing_funnel'],
            'campaigns' => $reports['campaigns'],
            'lead_sources' => $this->bi->buildDashboard($from, $to)['lead_sources'],
            'tables' => [
                [
                    'key' => 'campaigns',
                    'title' => 'عملکرد کمپین‌ها',
                    'columns' => [
                        ['key' => 'name', 'title' => 'کمپین'],
                        ['key' => 'leads_count', 'title' => 'لید'],
                        ['key' => 'converted_count', 'title' => 'تبدیل‌شده'],
                        ['key' => 'conversion_rate', 'title' => 'نرخ تبدیل (٪)'],
                        ['key' => 'cost_per_lead', 'title' => 'هزینه هر لید'],
                    ],
                    'rows' => $reports['campaigns'],
                ],
            ],
        ];
    }

    protected function customerInsights(?Carbon $from, ?Carbon $to, array $filters): array
    {
        $from = $from ?? now()->subMonths(6)->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $dashboard = $this->bi->buildDashboard($from, $to);

        $dormantDays = 30;
        $dormantContacts = Contact::query()
            ->when(! empty($filters['department']), fn ($q) => $q->where('department', $filters['department']))
            ->where('updated_at', '<', now()->subDays($dormantDays))
            ->limit(20)
            ->get(['id', 'name', 'company', 'city'])
            ->map(fn (Contact $c) => [
                'contact_id' => $c->id,
                'name' => $c->name,
                'company' => $c->company,
                'city' => $c->city,
            ])
            ->values()
            ->all();

        return [
            'template' => 'customer_insights',
            'title' => 'بینش مشتری',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_contacts' => Contact::count(),
                'top_ltv' => collect($dashboard['top_contacts'])->first()['ltv'] ?? 0,
                'dormant_contacts' => count($dormantContacts),
            ],
            'top_contacts' => $dashboard['top_contacts'],
            'dormant_contacts' => $dormantContacts,
            'tables' => [
                [
                    'key' => 'top_contacts',
                    'title' => 'مخاطبین با بیشترین ارزش',
                    'columns' => [
                        ['key' => 'name', 'title' => 'نام'],
                        ['key' => 'company', 'title' => 'شرکت'],
                        ['key' => 'deals_count', 'title' => 'معاملات'],
                        ['key' => 'ltv', 'title' => 'ارزش طول عمر'],
                    ],
                    'rows' => $dashboard['top_contacts'],
                ],
                [
                    'key' => 'dormant_contacts',
                    'title' => 'مخاطبین بدون فعالیت اخیر',
                    'columns' => [
                        ['key' => 'name', 'title' => 'نام'],
                        ['key' => 'company', 'title' => 'شرکت'],
                        ['key' => 'city', 'title' => 'شهر'],
                    ],
                    'rows' => $dormantContacts,
                ],
            ],
        ];
    }

    protected function operations(?Carbon $from, ?Carbon $to, array $filters): array
    {
        $from = $from ?? now()->subMonths(1)->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $dashboard = $this->bi->buildDashboard($from, $to);

        $taskQuery = Task::query()->whereNotNull('assignee_id');
        if (! empty($filters['assignee_id'])) {
            $taskQuery->where('assignee_id', $filters['assignee_id']);
        }
        $taskQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('created_at', [$from, $to])
                ->orWhereBetween('completed_at', [$from, $to]);
        });

        $activityQuery = Activity::query();
        if (! empty($filters['assignee_id'])) {
            $activityQuery->where('user_id', $filters['assignee_id']);
        }
        $activityQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('happened_at', [$from, $to])
                ->orWhereBetween('created_at', [$from, $to]);
        });

        $productivity = collect($dashboard['task_productivity']);
        if (! empty($filters['assignee_id'])) {
            $productivity = $productivity->where('user_id', (int) $filters['assignee_id'])->values();
        }

        return [
            'template' => 'operations',
            'title' => 'بهره‌وری عملیات',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_tasks' => (clone $taskQuery)->count(),
                'completed_tasks' => (clone $taskQuery)->where('status', 'completed')->count(),
                'total_activities' => (clone $activityQuery)->count(),
                'total_work_minutes' => $dashboard['summary']['total_work_minutes'],
            ],
            'activity_breakdown' => $dashboard['activity_breakdown'],
            'task_productivity' => $productivity->values()->all(),
            'tables' => [
                [
                    'key' => 'task_productivity',
                    'title' => 'بهره‌وری تسک به تفکیک کاربر',
                    'columns' => [
                        ['key' => 'name', 'title' => 'نام'],
                        ['key' => 'total', 'title' => 'کل تسک'],
                        ['key' => 'completed', 'title' => 'تکمیل‌شده'],
                        ['key' => 'completion_rate', 'title' => 'نرخ تکمیل (٪)'],
                        ['key' => 'work_minutes', 'title' => 'زمان کار (دقیقه)'],
                    ],
                    'rows' => $productivity->values()->all(),
                ],
            ],
        ];
    }

    protected function productsQuotes(?Carbon $from, ?Carbon $to, array $filters): array
    {
        $from = $from ?? now()->subMonths(3)->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $dashboard = $this->bi->buildDashboard($from, $to);

        $quoteQuery = Quote::query()->whereBetween('created_at', [$from, $to]);

        return [
            'template' => 'products_quotes',
            'title' => 'محصول و پیش‌فاکتور',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_quotes' => (clone $quoteQuery)->count(),
                'accepted_quotes' => (clone $quoteQuery)->where('status', 'accepted')->count(),
                'quote_acceptance_rate' => $dashboard['summary']['quote_acceptance_rate'],
            ],
            'quote_funnel' => $dashboard['quote_funnel'],
            'top_products' => $dashboard['top_products'],
            'tables' => [
                [
                    'key' => 'top_products',
                    'title' => 'محصولات پرتقاضا',
                    'columns' => [
                        ['key' => 'name', 'title' => 'محصول'],
                        ['key' => 'quote_revenue', 'title' => 'درآمد پیش‌فاکتور'],
                        ['key' => 'quote_quantity', 'title' => 'تعداد در پیش‌فاکتور'],
                        ['key' => 'linked_quantity', 'title' => 'اتصال به موجودیت'],
                    ],
                    'rows' => $dashboard['top_products'],
                ],
            ],
        ];
    }

    protected function filterTeamPerformance(array $rows, array $filters): array
    {
        $collection = collect($rows);

        if (! empty($filters['assignee_id'])) {
            $collection = $collection->where('user_id', (int) $filters['assignee_id']);
        }

        return $collection->values()->all();
    }
}
