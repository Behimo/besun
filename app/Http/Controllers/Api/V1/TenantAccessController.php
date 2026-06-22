<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Role;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Infrastructure\Services\TenantTeamService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TenantAccessController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
        protected TenantTeamService $teams,
    ) {}

    public function catalog(): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $catalog = config('crm_permissions.catalog', []);
        $groups = config('crm_permissions.group_labels', []);

        $permissions = collect($catalog)->map(fn (array $meta, string $name) => [
            'name' => $name,
            'label' => $meta['label'],
            'group' => $meta['group'],
            'group_label' => $groups[$meta['group']] ?? $meta['group'],
        ])->values();

        $roles = collect(RoleName::options())
            ->map(fn (array $role) => [...$role, 'is_custom' => false])
            ->concat($this->customRoles()->map(fn (object $role) => [
                'value' => $role->name,
                'label' => $role->label ?: $role->name,
                'department' => $role->department,
                'is_custom' => true,
            ]))
            ->values();

        return response()->json([
            'departments' => $this->teams->options($tenantId),
            'teams' => $this->teams->list($tenantId)->map(fn ($team) => $this->teams->format($team))->values(),
            'roles' => $roles,
            'permissions' => $permissions,
            'group_labels' => $groups,
        ]);
    }

    public function roles(): JsonResponse
    {
        $this->assertOwner();

        $tenantId = $this->tenantContext->tenantId();
        setPermissionsTeamId($tenantId);

        $builtin = collect(RoleName::manageableValues())
            ->map(function (string $roleName) use ($tenantId) {
                $role = Role::where('name', $roleName)->where('tenant_id', $tenantId)->first();
                $enum = RoleName::tryFrom($roleName);
                $parentRole = $enum?->parentRole()?->value;

                return [
                    'name' => $roleName,
                    'label' => $enum?->label() ?? $roleName,
                    'department' => $enum?->department()?->value,
                    'is_manager' => $enum?->isManager() ?? false,
                    'parent_role' => $parentRole,
                    'parent_role_label' => $parentRole ? RoleName::tryFrom($parentRole)?->label() : null,
                    'is_custom' => false,
                    'members_count' => $role ? $this->membersCount($role->id, $tenantId) : 0,
                    'permissions' => $this->permissions->rolePermissions($tenantId, $roleName),
                ];
            });

        $custom = $this->customRoles()->map(function (object $row) use ($tenantId) {
            $parentLabel = null;
            if ($row->parent_role ?? null) {
                $parentLabel = RoleName::tryFrom($row->parent_role)?->label()
                    ?? DB::table('roles')
                        ->where('tenant_id', $tenantId)
                        ->where('name', $row->parent_role)
                        ->value('label');
            }

            return [
                'name' => $row->name,
                'label' => $row->label ?: $row->name,
                'department' => $row->department,
                'is_manager' => (bool) $row->is_manager,
                'parent_role' => $row->parent_role ?? null,
                'parent_role_label' => $parentLabel,
                'is_custom' => true,
                'members_count' => $this->membersCount($row->id, $tenantId),
                'permissions' => $this->permissions->rolePermissions($tenantId, $row->name),
            ];
        });

        return response()->json(['roles' => $builtin->concat($custom)->values()]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'department' => ['nullable', $this->teams->teamRule($tenantId)],
            'is_manager' => ['nullable', 'boolean'],
            'parent_role' => ['nullable', 'string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        setPermissionsTeamId($tenantId);

        $parentRole = $this->resolveParentRole($data['parent_role'] ?? null, $tenantId);
        $department = $data['department'] ?? $parentRole['department'] ?? null;

        if ($parentRole && $department && $parentRole['department'] && $parentRole['department'] !== $department) {
            abort(422, 'نقش زیرمجموعه باید در همان تیم نقش اصلی باشد');
        }

        if ($parentRole && ($parentRole['is_manager'] ?? false) === false) {
            abort(422, 'فقط می‌توان زیرمجموعهٔ یک نقش اصلی ساخت');
        }

        if ($parentRole && ! $department) {
            $department = $parentRole['department'];
        }

        $isManager = (bool) ($data['is_manager'] ?? false);
        if ($parentRole) {
            $isManager = false;
        } elseif (! array_key_exists('is_manager', $data)) {
            $isManager = true;
        }

        $name = $this->uniqueRoleName($data['label'], $tenantId);

        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
            'label' => $data['label'],
            'department' => $department,
            'is_manager' => $isManager,
            'parent_role' => $parentRole['name'] ?? null,
        ]);

        $valid = $this->sanitizePermissions($data['permissions'] ?? ($parentRole['permissions'] ?? []));
        if ($valid === [] && $department && ! $parentRole) {
            $defaultManager = match ($department) {
                'sales' => RoleName::SalesManager->value,
                'marketing' => RoleName::MarketingManager->value,
                'finance' => RoleName::FinanceManager->value,
                default => null,
            };

            if ($defaultManager) {
                $valid = $this->sanitizePermissions($this->permissions->rolePermissions($tenantId, $defaultManager));
            } else {
                $existingManager = Role::query()
                    ->where('tenant_id', $tenantId)
                    ->where('department', $department)
                    ->where('is_manager', true)
                    ->whereNull('parent_role')
                    ->first();

                if ($existingManager) {
                    $valid = $this->sanitizePermissions($this->permissions->rolePermissions($tenantId, $existingManager->name));
                } else {
                    $valid = $this->sanitizePermissions(
                        config('crm_permissions.role_defaults.'.RoleName::SalesManager->value, [])
                    );
                }
            }
        }
        $permissionIds = Permission::whereIn('name', $valid)->pluck('id')->all();
        $role->syncPermissions($permissionIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'role' => [
                'name' => $role->name,
                'label' => $role->label,
                'department' => $role->department,
                'is_manager' => (bool) $role->is_manager,
                'parent_role' => $role->parent_role,
                'parent_role_label' => $parentRole['label'] ?? null,
                'is_custom' => true,
                'members_count' => 0,
                'permissions' => $valid,
            ],
        ], 201);
    }

    public function updateRole(Request $request, string $role): JsonResponse
    {
        $this->assertOwner();

        $tenantId = $this->tenantContext->tenantId();
        $isBuiltin = in_array($role, RoleName::manageableValues(), true);
        $customRole = $this->findCustomRole($role, $tenantId);

        if (! $isBuiltin && ! $customRole) {
            abort(404);
        }

        if ($role === RoleName::Owner->value) {
            abort(422, 'دسترسی‌های مالک قابل ویرایش نیست');
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
            'label' => ['sometimes', 'string', 'max:100'],
            'department' => ['sometimes', 'nullable', $this->teams->teamRule($tenantId)],
            'is_manager' => ['sometimes', 'boolean'],
            'parent_role' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $valid = $this->sanitizePermissions($data['permissions']);

        setPermissionsTeamId($tenantId);

        $spatieRole = Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        // Metadata is only editable on custom roles; built-ins come from the enum.
        if ($customRole) {
            $parentRole = array_key_exists('parent_role', $data)
                ? $this->resolveParentRole($data['parent_role'], $tenantId)
                : null;

            $department = array_key_exists('department', $data)
                ? $data['department']
                : $spatieRole->department;

            if ($parentRole && $department && $parentRole['department'] && $parentRole['department'] !== $department) {
                abort(422, 'نقش زیرمجموعه باید در همان تیم نقش اصلی باشد');
            }

            $spatieRole->update([
                'label' => $data['label'] ?? $spatieRole->label,
                'department' => $department,
                'is_manager' => array_key_exists('is_manager', $data) ? (bool) $data['is_manager'] : $spatieRole->is_manager,
                'parent_role' => array_key_exists('parent_role', $data)
                    ? ($parentRole['name'] ?? null)
                    : $spatieRole->parent_role,
            ]);
        }

        $permissionIds = Permission::whereIn('name', $valid)->pluck('id')->all();
        $spatieRole->syncPermissions($permissionIds);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'role' => [
                'name' => $role,
                'label' => $customRole ? $spatieRole->label : RoleName::tryFrom($role)?->label(),
                'department' => $customRole ? $spatieRole->department : RoleName::tryFrom($role)?->department()?->value,
                'is_manager' => $customRole ? (bool) $spatieRole->is_manager : (RoleName::tryFrom($role)?->isManager() ?? false),
                'parent_role' => $customRole ? $spatieRole->parent_role : RoleName::tryFrom($role)?->parentRole()?->value,
                'parent_role_label' => $this->parentRoleLabel($tenantId, $customRole ? $spatieRole->parent_role : RoleName::tryFrom($role)?->parentRole()?->value),
                'is_custom' => (bool) $customRole,
                'permissions' => $valid,
            ],
        ]);
    }

    public function destroyRole(string $role): JsonResponse
    {
        $this->assertOwner();

        $tenantId = $this->tenantContext->tenantId();
        $customRole = $this->findCustomRole($role, $tenantId);

        if (! $customRole) {
            abort(422, 'فقط نقش‌های سفارشی قابل حذف هستند');
        }

        if ($this->membersCount($customRole->id, $tenantId) > 0) {
            abort(422, 'ابتدا نقش اعضای دارای این نقش را تغییر دهید');
        }

        setPermissionsTeamId($tenantId);
        Role::find($customRole->id)?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Deleted.']);
    }

    protected function customRoles()
    {
        $query = DB::table('roles')
            ->where('tenant_id', $this->tenantContext->tenantId())
            ->whereNotIn('name', RoleName::manageableValues())
            ->whereNotIn('name', ['admin', 'employee', 'support']);

        if (Schema::hasColumn('roles', 'label')) {
            $query->orderBy('label');
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    protected function findCustomRole(string $name, int $tenantId): ?object
    {
        if (in_array($name, RoleName::manageableValues(), true)
            || in_array($name, ['admin', 'employee', 'support'], true)) {
            return null;
        }

        return DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->first();
    }

    protected function membersCount(int $roleId, int $tenantId): int
    {
        return DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenantId)
            ->count();
    }

    protected function sanitizePermissions(array $permissions): array
    {
        $catalog = array_keys(config('crm_permissions.catalog', []));

        return array_values(array_intersect($permissions, $catalog));
    }

    protected function resolveParentRole(?string $parentRoleName, int $tenantId): ?array
    {
        if (! $parentRoleName) {
            return null;
        }

        $enum = RoleName::tryFrom($parentRoleName);
        if ($enum && in_array($enum->value, RoleName::manageableValues(), true)) {
            if (! $enum->isManager()) {
                abort(422, 'فقط می‌توان زیرمجموعهٔ یک نقش اصلی ساخت');
            }

            return [
                'name' => $enum->value,
                'label' => $enum->label(),
                'department' => $enum->department()?->value,
                'is_manager' => $enum->isManager(),
                'permissions' => $this->permissions->rolePermissions($tenantId, $enum->value),
            ];
        }

        $custom = $this->findCustomRole($parentRoleName, $tenantId);
        if (! $custom) {
            abort(422, 'نقش اصلی انتخاب‌شده معتبر نیست');
        }

        if (! (bool) $custom->is_manager || ($custom->parent_role ?? null)) {
            abort(422, 'فقط می‌توان زیرمجموعهٔ یک نقش اصلی ساخت');
        }

        return [
            'name' => $custom->name,
            'label' => $custom->label ?: $custom->name,
            'department' => $custom->department,
            'is_manager' => (bool) $custom->is_manager,
            'permissions' => $this->permissions->rolePermissions($tenantId, $custom->name),
        ];
    }

    protected function parentRoleLabel(int $tenantId, ?string $parentRoleName): ?string
    {
        if (! $parentRoleName) {
            return null;
        }

        return RoleName::tryFrom($parentRoleName)?->label()
            ?? DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->where('name', $parentRoleName)
                ->value('label');
    }

    protected function uniqueRoleName(string $label, int $tenantId): string
    {
        $base = Str::slug($label, '_');

        if ($base === '' || ! preg_match('/[a-z]/', $base)) {
            $base = 'role';
        }

        $base = 'custom_'.$base;
        $name = $base;
        $suffix = 1;

        while (
            in_array($name, RoleName::manageableValues(), true)
            || DB::table('roles')->where('tenant_id', $tenantId)->where('name', $name)->exists()
        ) {
            $name = $base.'_'.(++$suffix);
        }

        return $name;
    }

    protected function assertOwner(): Tenant
    {
        $user = request()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenantId = $this->tenantContext->tenantId();

        if (! $tenantId) {
            abort(403, 'Tenant access denied.');
        }

        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->isOwner($user)) {
            abort(403, 'فقط مالک مجموعه به این بخش دسترسی دارد');
        }

        return $tenant;
    }
}
