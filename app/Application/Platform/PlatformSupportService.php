<?php

namespace App\Application\Platform;

use App\Application\Platform\PlatformSupportTicketService;
use App\Infrastructure\Persistence\Eloquent\Models\Invitation;
use App\Infrastructure\Persistence\Eloquent\Models\SubscriptionTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use App\Models\User;

class PlatformSupportService
{
    public function __construct(
        protected PlatformAdminService $adminService,
        protected PlatformSupportTicketService $tickets,
    ) {}

    public function dashboard(): array
    {
        $pendingSms = TenantSmsPanelRequest::with(['tenant:id,name,slug'])
            ->where('status', TenantSmsPanelRequest::STATUS_PENDING)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($req) => [
                'tenant_id' => $req->tenant_id,
                'tenant_name' => $req->tenant?->name,
                'company' => $req->company,
                'mobile_number' => $req->mobile_number,
                'created_at' => $req->created_at,
            ]);

        $needsHelp = $this->tenantsNeedingHelp();

        $recentSignups = Tenant::with('owner:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'owner_name' => $t->owner?->name,
                'owner_phone' => $t->owner?->phone,
                'has_core' => $t->hasActiveCoreModule(),
                'created_at' => $t->created_at,
            ]);

        $failedTransactions = SubscriptionTransaction::with('tenant:id,name')
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'tenant_name' => $tx->tenant?->name,
                'amount' => (float) $tx->amount,
                'created_at' => $tx->created_at,
            ]);

        $pendingInvitations = Invitation::with('tenant:id,name')
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($inv) => [
                'tenant_name' => $inv->tenant?->name,
                'phone' => $inv->phone,
                'created_at' => $inv->created_at,
            ]);

        return [
            'summary' => [
                'pending_sms' => TenantSmsPanelRequest::where('status', TenantSmsPanelRequest::STATUS_PENDING)->count(),
                'tenants_needing_help' => count($needsHelp),
                'failed_transactions_week' => SubscriptionTransaction::where('status', 'failed')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'open_tickets' => $this->tickets->openCount(),
            ],
            'pending_sms' => $pendingSms,
            'tenants_needing_help' => $needsHelp,
            'recent_signups' => $recentSignups,
            'failed_transactions' => $failedTransactions,
            'pending_invitations' => $pendingInvitations,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function tenantsNeedingHelp(): array
    {
        $tenants = Tenant::with(['owner:id,name,phone', 'subscription.modules'])->get();
        $results = [];

        foreach ($tenants as $tenant) {
            $reasons = [];

            if ($tenant->status === 'suspended') {
                $reasons[] = 'معلق';
            }

            if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast() && ! $tenant->hasActiveCoreModule()) {
                $reasons[] = 'آزمایشی منقضی';
            }

            if (! $tenant->hasActiveCoreModule()) {
                $reasons[] = 'بدون ماژول پایه';
            }

            $smsReq = TenantSmsPanelRequest::where('tenant_id', $tenant->id)->first();
            if ($smsReq?->status === TenantSmsPanelRequest::STATUS_REJECTED) {
                $reasons[] = 'SMS رد شده';
            }

            if ($reasons === []) {
                continue;
            }

            $results[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'owner_name' => $tenant->owner?->name,
                'owner_phone' => $tenant->owner?->phone,
                'reasons' => $reasons,
                'health_score' => $this->adminService->healthScore($tenant),
            ];
        }

        usort($results, fn ($a, $b) => $a['health_score'] <=> $b['health_score']);

        return array_slice($results, 0, 15);
    }

    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['tenants' => [], 'users' => []];
        }

        $tenants = Tenant::query()
            ->with('owner:id,name,phone')
            ->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'owner_phone' => $t->owner?->phone,
                'has_core' => $t->hasActiveCoreModule(),
            ]);

        $users = User::query()
            ->where('phone', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
            ]);

        return [
            'tenants' => $tenants,
            'users' => $users,
        ];
    }
}
