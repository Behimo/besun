<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\RoleName;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantPermissionSeeder
{
    public function seedForTenant(int $tenantId): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalog = config('crm_permissions.catalog', []);
        $roleDefaults = config('crm_permissions.role_defaults', []);

        foreach (array_keys($catalog) as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        setPermissionsTeamId($tenantId);

        foreach (RoleName::manageableValues() as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);

            $defaults = $roleDefaults[$roleName] ?? [];
            $permissionIds = Permission::whereIn('name', $defaults)->pluck('id')->all();
            $role->syncPermissions($permissionIds);
        }
    }

    public function seedAllTenants(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $this->seedForTenant((int) $tenantId);
        }
    }
}
