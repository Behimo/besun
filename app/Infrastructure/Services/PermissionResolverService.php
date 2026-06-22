<?php

namespace App\Infrastructure\Services;

use App\Domain\Shared\Enums\Department;
use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class PermissionResolverService
{
    /**
     * Resolves the user's role name within a tenant. Supports both the
     * built-in RoleName enum roles and owner-defined custom roles.
     */
    public function resolveRoleName(User $user, ?int $tenantId): ?string
    {
        if (! $tenantId) {
            return null;
        }

        $names = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.tenant_id', $tenantId)
            ->pluck('roles.name')
            ->all();

        if ($names === []) {
            return null;
        }

        // Prefer owner, then built-in roles in enum order, then custom roles.
        foreach (RoleName::cases() as $role) {
            if (in_array($role->value, $names, true)) {
                return $role->value;
            }
        }

        return $names[0];
    }

    public function resolveRole(User $user, ?int $tenantId): ?RoleName
    {
        return RoleName::tryFromValue($this->resolveRoleName($user, $tenantId));
    }

    /**
     * Metadata for any role name (built-in or custom) within a tenant.
     *
     * @return array{name: string, label: string, department: ?string, is_manager: bool, is_owner: bool, is_custom: bool}|null
     */
    public function roleMeta(?int $tenantId, ?string $roleName): ?array
    {
        if (! $tenantId || ! $roleName) {
            return null;
        }

        $enum = RoleName::tryFrom($roleName);

        if ($enum) {
            return [
                'name' => $enum->value,
                'label' => $enum->label(),
                'department' => $enum->department()?->value,
                'is_manager' => $enum->isManager(),
                'is_owner' => $enum->isOwner(),
                'is_custom' => false,
                'parent_role' => $enum->parentRole()?->value,
            ];
        }

        $row = DB::table('roles')
            ->where('name', $roleName)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'name' => $row->name,
            'label' => $row->label ?: $row->name,
            'department' => $row->department,
            'is_manager' => (bool) $row->is_manager,
            'is_owner' => false,
            'is_custom' => true,
            'parent_role' => $row->parent_role ?? null,
        ];
    }

    public function resolveRoleMeta(User $user, ?int $tenantId): ?array
    {
        return $this->roleMeta($tenantId, $this->resolveRoleName($user, $tenantId));
    }

    public function departmentFor(User $user, int $tenantId): ?string
    {
        $pivot = $this->membershipPivot($user, $tenantId);

        if ($pivot?->department) {
            return $pivot->department;
        }

        $meta = $this->resolveRoleMeta($user, $tenantId);

        return $meta['department'] ?? null;
    }

    public function overridesFor(User $user, int $tenantId): array
    {
        $pivot = $this->membershipPivot($user, $tenantId);
        $raw = $pivot?->permission_overrides;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return ['grant' => [], 'revoke' => []];
        }

        return [
            'grant' => array_values(array_unique($raw['grant'] ?? [])),
            'revoke' => array_values(array_unique($raw['revoke'] ?? [])),
        ];
    }

    public function rolePermissions(int $tenantId, RoleName|string $role): array
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;

        if ($roleName === RoleName::Owner->value) {
            return array_keys(config('crm_permissions.catalog', []));
        }

        setPermissionsTeamId($tenantId);

        $spatieRole = Role::where('name', $roleName)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($spatieRole && $spatieRole->permissions->isNotEmpty()) {
            return $spatieRole->permissions->pluck('name')->all();
        }

        return config("crm_permissions.role_defaults.{$roleName}", []);
    }

    public function effectivePermissions(User $user, ?int $tenantId): array
    {
        if (! $tenantId) {
            return [];
        }

        $roleName = $this->resolveRoleName($user, $tenantId);

        if (! $roleName) {
            return [];
        }

        if ($roleName === RoleName::Owner->value) {
            return array_keys(config('crm_permissions.catalog', []));
        }

        $base = $this->rolePermissions($tenantId, $roleName);
        $overrides = $this->overridesFor($user, $tenantId);

        $effective = array_unique(array_merge($base, $overrides['grant']));

        return array_values(array_diff($effective, $overrides['revoke']));
    }

    public function hasPermission(User $user, ?int $tenantId, string $permission): bool
    {
        if (! $tenantId) {
            return false;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant && $tenant->isOwner($user)) {
            return true;
        }

        if ($this->isOwnerRole($user, $tenantId)) {
            return true;
        }

        return in_array($permission, $this->effectivePermissions($user, $tenantId), true);
    }

    public function isOwnerRole(User $user, ?int $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        return $this->resolveRoleName($user, $tenantId) === RoleName::Owner->value;
    }

    /** Owner or any role flagged as manager (built-in or custom). */
    public function isManagerRole(User $user, ?int $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        $meta = $this->resolveRoleMeta($user, $tenantId);

        return $meta !== null && ($meta['is_owner'] || $meta['is_manager']);
    }

    public function isDepartmentManager(User $user, int $tenantId): bool
    {
        $meta = $this->resolveRoleMeta($user, $tenantId);

        return $meta !== null && $meta['is_manager'] && ! $meta['is_owner'];
    }

    public function canManageUsers(User $user, int $tenantId): bool
    {
        return $this->hasPermission($user, $tenantId, 'users.manage')
            || $this->isOwnerRole($user, $tenantId);
    }

    /** Role names assignable to members of a tenant (built-in + custom). */
    public function assignableRoleNames(int $tenantId): array
    {
        $custom = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('name', RoleName::manageableValues())
            ->whereNotIn('name', ['admin', 'employee', 'support'])
            ->pluck('name')
            ->all();

        return array_values(array_unique(array_merge(RoleName::assignableValues(), $custom)));
    }

    protected function membershipPivot(User $user, int $tenantId): ?object
    {
        return DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();
    }

}
