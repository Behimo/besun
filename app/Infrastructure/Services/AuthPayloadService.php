<?php

namespace App\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;

class AuthPayloadService
{
    public function __construct(
        protected AbilityService $abilityService,
        protected PermissionResolverService $permissions,
    ) {}

    public function payload(User $user): array
    {
        $user->loadMissing(['currentTenant.subscription.modules', 'currentWorkspace']);

        $tenantId = $user->in_tenant_shell ? $user->current_tenant_id : null;
        $userData = $this->userData($user);
        $abilityRules = $this->abilityService->rulesFor($user, $tenantId);

        return [
            'userData' => $userData,
            'userAbilityRules' => $abilityRules,
        ];
    }

    public function userData(User $user): array
    {
        $user->loadMissing(['currentTenant.subscription.modules', 'currentWorkspace']);

        $inShell = (bool) $user->in_tenant_shell;
        $tenant = $inShell ? $user->currentTenant : null;
        $tenantId = $tenant?->id;
        $activeModules = $tenant ? $tenant->activeModuleSlugs() : [];
        $roleMeta = $tenantId ? $this->permissions->resolveRoleMeta($user, $tenantId) : null;

        return [
            'id' => $user->id,
            'fullName' => $user->name,
            'username' => strstr($user->email ?? '', '@', true) ?: $user->phone,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $this->abilityService->roleNameFor($user, $tenantId),
            'roleLabel' => $roleMeta['label'] ?? null,
            'isManager' => (bool) ($roleMeta && ($roleMeta['is_owner'] || $roleMeta['is_manager'])),
            'department' => $tenantId ? $this->permissions->departmentFor($user, $tenantId) : null,
            'permissions' => $tenantId ? $this->permissions->effectivePermissions($user, $tenantId) : [],
            'inTenantShell' => $inShell,
            'currentTenantId' => $user->current_tenant_id,
            'currentWorkspaceId' => $user->current_workspace_id,
            'hasCoreModule' => $tenant ? $tenant->hasActiveCoreModule() : false,
            'activeModules' => $activeModules,
            'authType' => 'customer',
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'isOwner' => $tenant->isOwner($user),
                'canBroadcast' => $this->canBroadcast($user, $tenant),
                'canManageUsers' => $this->permissions->canManageUsers($user, $tenant->id),
            ] : null,
            'workspace' => $inShell && $user->currentWorkspace ? [
                'id' => $user->currentWorkspace->id,
                'name' => $user->currentWorkspace->name,
            ] : null,
        ];
    }

    protected function canBroadcast(User $user, Tenant $tenant): bool
    {
        return $tenant->isOwner($user)
            || $this->permissions->hasPermission($user, $tenant->id, 'broadcasts.send');
    }

    public function formatTenant(Tenant $tenant): array
    {
        $tenant->loadMissing(['subscription.plan', 'subscription.modules']);
        $coreModule = $tenant->subscription
            ? $tenant->subscription->modules()->where('plan_modules.is_core', true)->first()
            : null;
        $seatLimit = $tenant->subscription?->seat_limit;
        $seatPriceMonthly = $coreModule?->seat_monthly_price ?? $coreModule?->monthly_price;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'trial_ends_at' => $tenant->trial_ends_at,
            'has_core_module' => $tenant->hasActiveCoreModule(),
            'is_active' => $tenant->hasActiveSubscription(),
            'active_modules' => $tenant->activeModuleSlugs(),
            'plan' => $tenant->subscription?->plan?->name,
            'subscription_status' => $tenant->subscription?->status,
            'seat_limit' => $seatLimit,
            'seats_used' => $tenant->seatsUsed(),
            'seats_reserved' => $tenant->seatsReserved(),
            'core_expires_at' => $tenant->coreExpiresAt(),
            'core_subscription_type' => $tenant->coreSubscriptionType(),
            'core_remaining_days' => $tenant->coreRemainingDays(),
            'monthly_estimate' => $seatLimit && $seatPriceMonthly
                ? (float) $seatLimit * (float) $seatPriceMonthly
                : null,
        ];
    }
}
