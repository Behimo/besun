<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Infrastructure\Services\TenantTeamService;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
        protected TenantTeamService $teams,
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $this->assertCanManageUsers($tenantId);

        $users = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->with(['roles' => fn ($q) => $q->where('roles.tenant_id', $tenantId)])
            ->get()
            ->map(fn (User $user) => $this->formatMember($user, $tenantId));

        return response()->json(['users' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $this->assertOwner($tenantId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^09\d{9}$/'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:'.implode(',', $this->permissions->assignableRoleNames($tenantId))],
            'department' => ['nullable', $this->teams->teamRule($tenantId)],
        ]);

        $data['phone'] = PhoneNormalizer::normalize($data['phone']);
        $workspaceId = $this->tenantContext->workspaceId();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'current_tenant_id' => $tenantId,
            'current_workspace_id' => $workspaceId,
        ]);

        $department = $this->resolveDepartment($data['role'], $data['department'] ?? null, $tenantId);

        $user->tenants()->attach($tenantId, [
            'joined_at' => now(),
            'department' => $department,
            'permission_overrides' => json_encode(['grant' => [], 'revoke' => []]),
        ]);

        if ($workspaceId) {
            $user->workspaces()->attach($workspaceId);
        }

        $this->assignRole($user, $tenantId, $data['role']);

        return response()->json(['user' => $this->formatMember($user->fresh(), $tenantId)], 201);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        return $this->updateAccess($request, $user);
    }

    public function showAccess(User $user): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $this->assertOwner($tenantId);

        if (! $user->belongsToTenant($tenantId)) {
            abort(403);
        }

        $roleName = $this->permissions->resolveRoleName($user, $tenantId);
        $overrides = $this->permissions->overridesFor($user, $tenantId);
        $effectivePermissions = $this->permissions->effectivePermissions($user, $tenantId);

        return response()->json([
            'user' => $this->formatMember($user, $tenantId),
            'role_permissions' => $roleName ? $this->permissions->rolePermissions($tenantId, $roleName) : [],
            'effective_permissions' => $effectivePermissions,
            'overrides' => $overrides,
            'catalog' => config('crm_permissions.catalog', []),
            'group_labels' => config('crm_permissions.group_labels', []),
        ]);
    }

    public function updateAccess(Request $request, User $user): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $this->assertOwner($tenantId);

        if (! $user->belongsToTenant($tenantId)) {
            abort(403);
        }

        $tenant = Tenant::findOrFail($tenantId);

        if ($tenant->isOwner($user)) {
            abort(422, 'دسترسی مالک مجموعه قابل تغییر نیست');
        }

        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', $this->permissions->assignableRoleNames($tenantId))],
            'department' => ['nullable', $this->teams->teamRule($tenantId)],
            'permission_overrides' => ['nullable', 'array'],
            'permission_overrides.grant' => ['nullable', 'array'],
            'permission_overrides.grant.*' => ['string'],
            'permission_overrides.revoke' => ['nullable', 'array'],
            'permission_overrides.revoke.*' => ['string'],
        ]);

        $department = $this->resolveDepartment($data['role'], $data['department'] ?? null, $tenantId);
        $overrides = $this->sanitizeOverrides($data['permission_overrides'] ?? []);

        DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->update([
                'department' => $department,
                'permission_overrides' => json_encode($overrides),
            ]);

        $this->assignRole($user, $tenantId, $data['role']);

        return response()->json(['user' => $this->formatMember($user->fresh(), $tenantId)]);
    }

    protected function formatMember(User $user, int $tenantId): array
    {
        setPermissionsTeamId($tenantId);
        $user->load(['roles' => fn ($q) => $q->where('roles.tenant_id', $tenantId)]);

        $role = $user->roles->first()?->name;
        $roleMeta = $this->permissions->roleMeta($tenantId, $role);
        $pivot = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        $overrides = $pivot?->permission_overrides;
        if (is_string($overrides)) {
            $overrides = json_decode($overrides, true);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $role,
            'role_label' => $roleMeta['label'] ?? $role,
            'is_manager' => (bool) ($roleMeta['is_manager'] ?? false),
            'department' => $pivot?->department,
            'department_label' => $this->teams->label($tenantId, $pivot?->department),
            'permission_overrides' => $overrides ?? ['grant' => [], 'revoke' => []],
            'permissions' => $this->permissions->effectivePermissions($user, $tenantId),
        ];
    }

    protected function assignRole(User $user, int $tenantId, string $role): void
    {
        setPermissionsTeamId($tenantId);
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
        $user->syncRoles([$role]);
    }

    protected function resolveDepartment(string $role, ?string $department, ?int $tenantId = null): ?string
    {
        if ($department) {
            return $department;
        }

        return $this->permissions->roleMeta($tenantId ?? $this->tenantContext->tenantId(), $role)['department'] ?? null;
    }

    protected function sanitizeOverrides(array $overrides): array
    {
        $catalog = array_keys(config('crm_permissions.catalog', []));

        $grant = array_values(array_intersect($overrides['grant'] ?? [], $catalog));
        $revoke = array_values(array_intersect($overrides['revoke'] ?? [], $catalog));

        return ['grant' => $grant, 'revoke' => $revoke];
    }

    protected function assertOwner(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->isOwner(request()->user())) {
            abort(403, 'فقط مالک مجموعه این عملیات را انجام می‌دهد');
        }
    }

    protected function assertCanManageUsers(int $tenantId): void
    {
        if (! $this->permissions->canManageUsers(request()->user(), $tenantId)) {
            abort(403);
        }
    }

}
