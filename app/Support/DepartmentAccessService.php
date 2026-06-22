<?php

namespace App\Support;

use App\Domain\Shared\Enums\Department;
use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartmentAccessService
{
    public function __construct(
        protected PermissionResolverService $permissions,
    ) {}

    public function isOwner(User $user, Tenant $tenant): bool
    {
        return $tenant->isOwner($user)
            || $this->permissions->isOwnerRole($user, $tenant->id);
    }

    public function departmentFor(User $user, int $tenantId): ?string
    {
        return $this->permissions->departmentFor($user, $tenantId);
    }

    /**
     * IDs of all active tenant members that belong to a team, either via
     * the tenant_user pivot or via a role tied to that team.
     *
     * @return array<int, int>
     */
    public function departmentMemberIds(int $tenantId, string $teamSlug): array
    {
        $pivotIds = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->whereNull('left_at')
            ->where('department', $teamSlug)
            ->pluck('user_id')
            ->all();

        $builtinNames = collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $role->department()?->value === $teamSlug)
            ->map(fn (RoleName $role) => $role->value)
            ->all();

        $customNames = Schema::hasColumn('roles', 'department')
            ? DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->where('department', $teamSlug)
                ->pluck('name')
                ->all()
            : [];

        $roleNames = array_merge($builtinNames, $customNames);

        setPermissionsTeamId($tenantId);

        $roleIds = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId)->whereNull('tenant_user.left_at'))
            ->whereHas('roles', fn ($q) => $q->where('roles.tenant_id', $tenantId)->whereIn('name', $roleNames))
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($pivotIds, $roleIds))));
    }

    public function scopeDepartmentRecords(Builder|Relation $query, User $user, int $tenantId, string $assignedColumn = 'assigned_to'): Builder|Relation
    {
        if ($this->isOwner($user, Tenant::find($tenantId))) {
            return $query;
        }

        $model = $this->modelForQuery($query);

        if ($model instanceof Lead) {
            return $this->scopeLeadRecords($query, $user, $tenantId, $assignedColumn);
        }

        if ($model instanceof Deal) {
            return $this->scopeDealRecords($query, $user, $tenantId, $assignedColumn);
        }

        return $this->scopeLegacyDepartmentRecords($query, $user, $tenantId, $assignedColumn);
    }

    public function scopeLeadRecords(Builder|Relation $query, User $user, int $tenantId, string $assignedColumn = 'assigned_to'): Builder|Relation
    {
        if ($this->isOwner($user, Tenant::find($tenantId))) {
            return $query;
        }

        $canViewUnassigned = $this->permissions->hasPermission($user, $tenantId, 'leads.view_unassigned');

        return $query->where(function ($q) use ($user, $assignedColumn, $canViewUnassigned) {
            $q->where(function ($own) use ($user, $assignedColumn) {
                $own->where('department', Department::Marketing->value)
                    ->where($assignedColumn, $user->id);
            });

            if ($canViewUnassigned) {
                $q->orWhereNull($assignedColumn);
            }
        });
    }

    public function scopeDealRecords(Builder|Relation $query, User $user, int $tenantId, string $assignedColumn = 'assigned_to'): Builder|Relation
    {
        if ($this->isOwner($user, Tenant::find($tenantId))) {
            return $query;
        }

        $canViewUnassigned = $this->permissions->hasPermission($user, $tenantId, 'deals.view_unassigned');

        return $query
            ->where('department', Department::Sales->value)
            ->where(function ($q) use ($user, $assignedColumn, $canViewUnassigned) {
                $q->where($assignedColumn, $user->id);

                if ($canViewUnassigned) {
                    $q->orWhereNull($assignedColumn);
                }
            });
    }

    public function scopeLegacyDepartmentRecords(Builder|Relation $query, User $user, int $tenantId, string $assignedColumn = 'assigned_to'): Builder|Relation
    {
        $department = $this->departmentFor($user, $tenantId);
        $isManager = $this->permissions->isManagerRole($user, $tenantId);

        if (! $department) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('department', $department);

        if ($isManager) {
            return $query;
        }

        return $query->where(function ($q) use ($user, $assignedColumn) {
            $q->where($assignedColumn, $user->id)
                ->orWhereNull($assignedColumn);
        });
    }

    public function canViewRecord(User $user, int $tenantId, Model $record, string $assignedColumn = 'assigned_to'): bool
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant && $this->isOwner($user, $tenant)) {
            return true;
        }

        if ($record instanceof Lead) {
            return $this->canViewLeadRecord($user, $tenantId, $record, $assignedColumn);
        }

        if ($record instanceof Deal) {
            return $this->canViewDealRecord($user, $tenantId, $record, $assignedColumn);
        }

        return $this->canViewLegacyDepartmentRecord($user, $tenantId, $record, $assignedColumn);
    }

    protected function canViewLeadRecord(User $user, int $tenantId, Lead $record, string $assignedColumn = 'assigned_to'): bool
    {
        $assigned = $record->{$assignedColumn};

        if ($record->department === Department::Marketing->value && (int) $assigned === (int) $user->id) {
            return true;
        }

        return $assigned === null
            && $this->permissions->hasPermission($user, $tenantId, 'leads.view_unassigned');
    }

    protected function canViewDealRecord(User $user, int $tenantId, Deal $record, string $assignedColumn = 'assigned_to'): bool
    {
        if ($record->department !== Department::Sales->value) {
            return false;
        }

        $assigned = $record->{$assignedColumn};

        return (int) $assigned === (int) $user->id
            || ($assigned === null && $this->permissions->hasPermission($user, $tenantId, 'deals.view_unassigned'));
    }

    protected function canViewLegacyDepartmentRecord(User $user, int $tenantId, Model $record, string $assignedColumn = 'assigned_to'): bool
    {
        $department = $this->departmentFor($user, $tenantId);

        if (! $department || $record->department !== $department) {
            return false;
        }

        if ($this->permissions->isManagerRole($user, $tenantId)) {
            return true;
        }

        $assigned = $record->{$assignedColumn};

        return $assigned === null || (int) $assigned === (int) $user->id;
    }

    protected function modelForQuery(Builder|Relation $query): ?Model
    {
        if ($query instanceof Builder) {
            return $query->getModel();
        }

        return method_exists($query, 'getRelated') ? $query->getRelated() : null;
    }

    public function defaultDepartmentForPipeline(?string $pipelineType): ?string
    {
        return match ($pipelineType) {
            'sales' => Department::Sales->value,
            'marketing' => Department::Marketing->value,
            default => null,
        };
    }
}
