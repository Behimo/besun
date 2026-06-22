<?php

namespace App\Application\Platform;

use App\Infrastructure\Persistence\Eloquent\Models\SubscriptionTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use App\Infrastructure\Services\AuthPayloadService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PlatformAdminService
{
    public function __construct(
        protected AuthPayloadService $authPayload,
    ) {}

    public function listTenants(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $query = Tenant::query()
            ->with(['owner:id,name,phone,email', 'subscription.plan', 'subscription.modules'])
            ->withCount('users')
            ->orderByDesc('created_at');

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($oq) => $oq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if (($filters['core'] ?? null) === 'active') {
            $query->whereHas('subscription.modules', function ($q) {
                $q->where('plan_modules.is_core', true)
                    ->where('subscription_modules.status', 'active')
                    ->where(function ($inner) {
                        $inner->whereNull('subscription_modules.expires_at')
                            ->orWhere('subscription_modules.expires_at', '>', now());
                    });
            });
        } elseif (($filters['core'] ?? null) === 'inactive') {
            $query->whereDoesntHave('subscription.modules', function ($q) {
                $q->where('plan_modules.is_core', true)
                    ->where('subscription_modules.status', 'active')
                    ->where(function ($inner) {
                        $inner->whereNull('subscription_modules.expires_at')
                            ->orWhere('subscription_modules.expires_at', '>', now());
                    });
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function formatTenantListItem(Tenant $tenant): array
    {
        $lastTx = SubscriptionTransaction::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->first();

        $smsRequest = TenantSmsPanelRequest::where('tenant_id', $tenant->id)->first();

        return array_merge(
            $this->authPayload->formatTenant($tenant),
            [
                'owner' => $tenant->owner ? [
                    'id' => $tenant->owner->id,
                    'name' => $tenant->owner->name,
                    'phone' => $tenant->owner->phone,
                ] : null,
                'members_count' => $tenant->users_count ?? $tenant->seatsUsed(),
                'health_score' => $this->healthScore($tenant),
                'health_label' => $this->healthLabel($tenant),
                'last_transaction' => $lastTx ? [
                    'amount' => (float) $lastTx->amount,
                    'status' => $lastTx->status,
                    'created_at' => $lastTx->created_at,
                ] : null,
                'sms_request_status' => $smsRequest?->status,
            ],
        );
    }

    public function tenantDetail(Tenant $tenant): array
    {
        $tenant->load([
            'owner:id,name,phone,email',
            'subscription.plan',
            'subscription.modules',
            'users:id,name,phone,email',
        ]);

        $transactions = SubscriptionTransaction::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (SubscriptionTransaction $tx) => [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'status' => $tx->status,
                'summary' => $this->summarizeItems($tx->items),
                'paid_at' => $tx->paid_at,
                'created_at' => $tx->created_at,
            ]);

        $smsRequest = TenantSmsPanelRequest::where('tenant_id', $tenant->id)->first();

        return [
            'tenant' => array_merge(
                $this->formatTenantListItem($tenant),
                [
                    'members' => $tenant->users->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'phone' => $u->phone,
                        'email' => $u->email,
                    ]),
                ],
            ),
            'transactions' => $transactions,
            'sms_request' => $smsRequest ? [
                'status' => $smsRequest->status,
                'company' => $smsRequest->company,
                'mobile_number' => $smsRequest->mobile_number,
                'rejection_reason' => $smsRequest->rejection_reason,
                'created_at' => $smsRequest->created_at,
            ] : null,
        ];
    }

    public function updateTenantStatus(Tenant $tenant, string $status): Tenant
    {
        $tenant->update(['status' => $status]);

        return $tenant->fresh();
    }

    public function listTransactions(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $query = SubscriptionTransaction::query()
            ->with(['tenant:id,name,slug', 'user:id,name,phone'])
            ->orderByDesc('created_at');

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    ->orWhere('gateway_reference', 'like', "%{$search}%");
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($tenantId = $filters['tenant_id'] ?? null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($from = $filters['from'] ?? null) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $filters['to'] ?? null) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function formatTransaction(SubscriptionTransaction $tx): array
    {
        return [
            'id' => $tx->id,
            'tenant_id' => $tx->tenant_id,
            'tenant_name' => $tx->tenant?->name,
            'tenant_slug' => $tx->tenant?->slug,
            'user_id' => $tx->user_id,
            'user_name' => $tx->user?->name,
            'user_phone' => $tx->user?->phone,
            'amount' => (float) $tx->amount,
            'status' => $tx->status,
            'gateway_reference' => $tx->gateway_reference,
            'items' => $tx->items,
            'summary' => $this->summarizeItems($tx->items),
            'paid_at' => $tx->paid_at,
            'created_at' => $tx->created_at,
        ];
    }

    public function healthScore(Tenant $tenant): int
    {
        $score = 50;

        if ($tenant->hasActiveCoreModule()) {
            $score += 25;
        } else {
            $score -= 20;
        }

        if ($tenant->status === 'active') {
            $score += 10;
        } elseif ($tenant->status === 'suspended') {
            $score -= 30;
        }

        $seatLimit = $tenant->seatLimit();
        if ($seatLimit) {
            $usage = $tenant->seatsReserved() / max(1, $seatLimit);
            if ($usage >= 0.9) {
                $score -= 5;
            } elseif ($usage >= 0.5) {
                $score += 5;
            }
        }

        $recentPaid = SubscriptionTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(90))
            ->exists();

        if ($recentPaid) {
            $score += 15;
        }

        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast() && ! $tenant->hasActiveCoreModule()) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    public function healthLabel(Tenant $tenant): string
    {
        $score = $this->healthScore($tenant);

        return match (true) {
            $score >= 80 => 'عالی',
            $score >= 60 => 'خوب',
            $score >= 40 => 'نیازمند توجه',
            default => 'بحرانی',
        };
    }

    protected function summarizeItems(?array $items): string
    {
        if (! $items) {
            return '';
        }

        $parts = [];

        if (! empty($items['seat_count'])) {
            $parts[] = $items['seat_count'].' کارمند';
        }

        foreach ($items['modules'] ?? [] as $module) {
            $parts[] = $module['title'] ?? 'ماژول';
        }

        return implode(' · ', $parts);
    }
}
