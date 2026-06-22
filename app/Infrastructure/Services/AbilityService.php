<?php

namespace App\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;

class AbilityService
{
    public function __construct(
        protected PermissionResolverService $permissions,
    ) {}

    public function rulesFor(User $user, ?int $tenantId = null): array
    {
        if (! $tenantId) {
            return [
                ['action' => 'read', 'subject' => 'Dashboard'],
                ['action' => 'manage', 'subject' => 'Tenants'],
                ['action' => 'manage', 'subject' => 'Billing'],
            ];
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant && $tenant->isOwner($user)) {
            return [
                ['action' => 'manage', 'subject' => 'all'],
                ['action' => 'manage', 'subject' => 'TenantSettings'],
            ];
        }

        $meta = $this->permissions->resolveRoleMeta($user, $tenantId);

        if ($meta && $meta['is_owner']) {
            return [
                ['action' => 'manage', 'subject' => 'all'],
                ['action' => 'manage', 'subject' => 'TenantSettings'],
            ];
        }

        $effective = $this->permissions->effectivePermissions($user, $tenantId);
        $catalog = config('crm_permissions.catalog', []);
        $rules = [];
        $seen = [];

        foreach ($effective as $permission) {
            $casl = $catalog[$permission]['casl'] ?? null;

            if (! $casl) {
                continue;
            }

            $key = $casl['action'].'|'.$casl['subject'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rules[] = $casl;
        }

        if ($meta && $meta['is_manager'] && ! isset($seen['read|BI'])) {
            $rules[] = ['action' => 'read', 'subject' => 'BI'];
        }

        return $rules;
    }

    public function roleNameFor(User $user, ?int $tenantId): string
    {
        return $this->permissions->resolveRoleName($user, $tenantId) ?? 'sales_employee';
    }
}
