<?php

namespace App\Application\Platform;

use App\Infrastructure\Persistence\Eloquent\Models\Invitation;
use App\Infrastructure\Persistence\Eloquent\Models\MarketingLead;
use App\Infrastructure\Persistence\Eloquent\Models\SubscriptionTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformReportsService
{
    public function dashboardSummary(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $todayStart = $now->copy()->startOfDay();

        $tenants = Tenant::query()->with('subscription.modules')->get();
        $activeCore = $tenants->filter(fn (Tenant $t) => $t->hasActiveCoreModule())->count();
        $inactiveCore = $tenants->count() - $activeCore;
        $inTrial = $tenants->filter(fn (Tenant $t) => $t->trial_ends_at && $t->trial_ends_at->isFuture())->count();
        $newThisMonth = Tenant::where('created_at', '>=', $monthStart)->count();

        $paidQuery = SubscriptionTransaction::query()->where('status', 'paid');
        $totalRevenue = (float) (clone $paidQuery)->sum('amount');
        $monthRevenue = (float) (clone $paidQuery)->where('paid_at', '>=', $monthStart)->sum('amount');
        $todayTransactions = SubscriptionTransaction::where('created_at', '>=', $todayStart)->count();
        $failedTransactions = SubscriptionTransaction::where('status', 'failed')
            ->where('created_at', '>=', $monthStart)
            ->count();

        $pendingSms = TenantSmsPanelRequest::where('status', TenantSmsPanelRequest::STATUS_PENDING)->count();
        $pendingInvitations = Invitation::where('status', 'pending')
            ->where('expires_at', '>', $now)
            ->count();
        $marketingLeadsTotal = MarketingLead::count();
        $marketingLeadsMonth = MarketingLead::where('created_at', '>=', $monthStart)->count();

        $tenantCount = max(1, $tenants->count());

        return [
            'tenants' => [
                'total' => $tenants->count(),
                'active_core' => $activeCore,
                'inactive_core' => $inactiveCore,
                'in_trial' => $inTrial,
                'new_this_month' => $newThisMonth,
                'suspended' => $tenants->where('status', 'suspended')->count(),
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'month' => $monthRevenue,
                'mrr_estimate' => $this->estimateMrr(),
                'arpu' => round($monthRevenue / $tenantCount, 0),
            ],
            'operations' => [
                'transactions_today' => $todayTransactions,
                'failed_transactions_month' => $failedTransactions,
                'pending_sms_requests' => $pendingSms,
                'pending_invitations' => $pendingInvitations,
            ],
            'marketing' => [
                'leads_total' => $marketingLeadsTotal,
                'leads_this_month' => $marketingLeadsMonth,
            ],
        ];
    }

    public function buildReports(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subMonths(11)->startOfMonth();
        $to ??= now()->endOfDay();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'revenue_trend' => $this->revenueTrend($from, $to),
            'tenant_growth' => $this->tenantGrowth($from, $to),
            'module_adoption' => $this->moduleAdoption(),
            'top_tenants' => $this->topTenantsByRevenue(10, $from, $to),
            'transaction_status_breakdown' => $this->transactionStatusBreakdown($from, $to),
        ];
    }

    protected function estimateMrr(): float
    {
        $monthStart = now()->startOfMonth();

        return (float) SubscriptionTransaction::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');
    }

    /** @return array<int, array{month: string, revenue: float, count: int}> */
    protected function revenueTrend(Carbon $from, Carbon $to): array
    {
        $rows = SubscriptionTransaction::query()
            ->selectRaw("DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') as month")
            ->selectRaw('SUM(amount) as revenue')
            ->selectRaw('COUNT(*) as count')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(fn ($row) => [
            'month' => $row->month,
            'revenue' => (float) $row->revenue,
            'count' => (int) $row->count,
        ])->all();
    }

    /** @return array<int, array{month: string, count: int}> */
    protected function tenantGrowth(Carbon $from, Carbon $to): array
    {
        $rows = Tenant::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(fn ($row) => [
            'month' => $row->month,
            'count' => (int) $row->count,
        ])->all();
    }

    /** @return array<int, array{slug: string, name: string, tenants: int, rate: float}> */
    protected function moduleAdoption(): array
    {
        $totalTenants = max(1, Tenant::count());
        $modules = config('crm_modules.modules', []);
        $slugToName = collect($modules)->pluck('name', 'slug');

        $rows = DB::table('subscription_modules')
            ->join('plan_modules', 'plan_modules.id', '=', 'subscription_modules.plan_module_id')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_modules.subscription_id')
            ->where('subscription_modules.status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscription_modules.expires_at')
                    ->orWhere('subscription_modules.expires_at', '>', now());
            })
            ->selectRaw('plan_modules.slug, COUNT(DISTINCT subscriptions.tenant_id) as tenant_count')
            ->groupBy('plan_modules.slug')
            ->orderByDesc('tenant_count')
            ->get();

        return $rows->map(fn ($row) => [
            'slug' => $row->slug,
            'name' => $slugToName[$row->slug] ?? $row->slug,
            'tenants' => (int) $row->tenant_count,
            'rate' => round(((int) $row->tenant_count / $totalTenants) * 100, 1),
        ])->all();
    }

    /** @return array<int, array{tenant_id: int, tenant_name: string, revenue: float, transactions: int}> */
    protected function topTenantsByRevenue(int $limit, Carbon $from, Carbon $to): array
    {
        $rows = SubscriptionTransaction::query()
            ->select('tenant_id')
            ->selectRaw('SUM(amount) as revenue')
            ->selectRaw('COUNT(*) as count')
            ->where('status', 'paid')
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$from, $to])
            ->groupBy('tenant_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $tenantNames = Tenant::whereIn('id', $rows->pluck('tenant_id'))
            ->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'tenant_id' => $row->tenant_id,
            'tenant_name' => $tenantNames[$row->tenant_id] ?? '—',
            'revenue' => (float) $row->revenue,
            'transactions' => (int) $row->count,
        ])->all();
    }

    /** @return array<string, int> */
    protected function transactionStatusBreakdown(Carbon $from, Carbon $to): array
    {
        return SubscriptionTransaction::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function exportTransactions(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = SubscriptionTransaction::query()
            ->with(['tenant:id,name', 'user:id,name,phone'])
            ->orderByDesc('created_at');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(fn (SubscriptionTransaction $tx) => [
            'id' => $tx->id,
            'tenant' => $tx->tenant?->name,
            'user' => $tx->user?->name,
            'phone' => $tx->user?->phone,
            'amount' => (float) $tx->amount,
            'status' => $tx->status,
            'paid_at' => $tx->paid_at?->toIso8601String(),
            'created_at' => $tx->created_at?->toIso8601String(),
        ])->all();
    }
}
