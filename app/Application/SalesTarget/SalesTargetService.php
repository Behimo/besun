<?php

namespace App\Application\SalesTarget;

use App\Domain\Shared\Enums\Department;
use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\SalesTarget;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Models\User;
use App\Support\DepartmentAccessService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class SalesTargetService
{
    public function __construct(
        protected PermissionResolverService $permissions,
        protected DepartmentAccessService $departments,
    ) {}

    public function list(User $user, int $tenantId, int $jyear, int $jmonth): array
    {
        $canManageManagerTargets = $this->canManageManagerTargets($user, $tenantId);
        $canManageUserTargets = $this->canManageUserTargets($user, $tenantId);

        $targets = SalesTarget::query()
            ->with(['user:id,name', 'setter:id,name'])
            ->where('jyear', $jyear)
            ->where('jmonth', $jmonth)
            ->where('scope', SalesTarget::SCOPE_USER)
            ->orderBy('user_id')
            ->get();

        $period = $this->periodBounds($jyear, $jmonth);
        $wonStageIds = $this->wonStageIds();
        $salesMemberIds = $this->departments->departmentMemberIds($tenantId, Department::Sales->value);
        $teamActual = $this->actualForUsers($salesMemberIds, $wonStageIds, $period['from'], $period['to']);

        $rows = [];

        if ($canManageManagerTargets) {
            $managerIds = $this->salesManagerIds($tenantId);
            $managers = User::query()
                ->whereIn('id', $managerIds)
                ->orderBy('name')
                ->get(['id', 'name']);

            foreach ($managers as $manager) {
                $rows[] = $this->formatRow(
                    $targets->firstWhere('user_id', $manager->id),
                    "مدیر فروش — {$manager->name}",
                    $manager->id,
                    $teamActual,
                    true,
                    'manager',
                );
            }
        } elseif ($canManageUserTargets) {
            $managerIds = $this->salesManagerIds($tenantId);

            if (in_array($user->id, $managerIds, true)) {
                $rows[] = $this->formatRow(
                    $targets->firstWhere('user_id', $user->id),
                    $user->name,
                    $user->id,
                    $teamActual,
                    false,
                    'manager',
                );
            }

            $repIds = $this->salesRepIds($tenantId);
            $members = User::query()
                ->whereIn('id', $repIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->keyBy('id');

            foreach ($repIds as $repId) {
                $member = $members->get($repId);
                if (! $member)
                    continue;

                $rows[] = $this->formatRow(
                    $targets->firstWhere('user_id', $repId),
                    $member->name,
                    $repId,
                    $this->actualForUsers([$repId], $wonStageIds, $period['from'], $period['to']),
                    true,
                    'rep',
                );
            }
        } else {
            $repIds = $this->salesRepIds($tenantId);

            if (in_array($user->id, $repIds, true)) {
                $rows[] = $this->formatRow(
                    $targets->firstWhere('user_id', $user->id),
                    $user->name,
                    $user->id,
                    $this->actualForUsers([$user->id], $wonStageIds, $period['from'], $period['to']),
                    false,
                    'rep',
                );
            }
        }

        return [
            'period' => ['jyear' => $jyear, 'jmonth' => $jmonth],
            'can_manage_manager_targets' => $canManageManagerTargets,
            'can_manage_user_targets' => $canManageUserTargets,
            'can_manage_department' => $canManageManagerTargets,
            'can_manage_users' => $canManageUserTargets,
            'rows' => $rows,
            'empty_hint' => $this->emptyHint($user, $tenantId, $rows, $canManageManagerTargets, $canManageUserTargets),
        ];
    }

    public function upsert(User $user, int $tenantId, array $data): SalesTarget
    {
        $jyear = (int) $data['jyear'];
        $jmonth = (int) $data['jmonth'];
        $scope = $data['scope'];

        if ($scope === SalesTarget::SCOPE_DEPARTMENT) {
            abort(403, 'تارگت تیم فروش از طریق مدیر فروش تنظیم می‌شود.');
        }

        if ($scope === SalesTarget::SCOPE_USER) {
            $assigneeId = (int) $data['user_id'];
            $canSetManager = $this->canManageManagerTargets($user, $tenantId)
                && in_array($assigneeId, $this->salesManagerIds($tenantId), true);
            $canSetRep = $this->canManageUserTargets($user, $tenantId)
                && in_array($assigneeId, $this->salesRepIds($tenantId), true);

            if (! $canSetManager && ! $canSetRep) {
                abort(403, 'مجوز تنظیم تارگت برای این کاربر را ندارید.');
            }

            $target = SalesTarget::query()->firstOrNew([
                'scope' => SalesTarget::SCOPE_USER,
                'department' => null,
                'user_id' => $assigneeId,
                'jyear' => $jyear,
                'jmonth' => $jmonth,
            ]);

            $target->fill([
                'revenue_target' => $data['revenue_target'] ?? 0,
                'deals_target' => $data['deals_target'] ?? null,
                'notes' => $data['notes'] ?? null,
                'set_by' => $user->id,
            ]);
            $target->save();

            return $target->load(['user', 'setter']);
        }

        throw ValidationException::withMessages(['scope' => ['نوع تارگت نامعتبر است.']]);
    }

    protected function canManageManagerTargets(User $user, int $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);

        return $tenant && $this->departments->isOwner($user, $tenant);
    }

    protected function canManageUserTargets(User $user, int $tenantId): bool
    {
        if ($this->permissions->isOwnerRole($user, $tenantId)) {
            return false;
        }

        $role = $this->permissions->resolveRoleName($user, $tenantId);

        return $role === RoleName::SalesManager->value
            || (
                $this->permissions->isDepartmentManager($user, $tenantId)
                && $this->permissions->departmentFor($user, $tenantId) === Department::Sales->value
            );
    }

    /** @return array<int, int> */
    protected function salesManagerIds(int $tenantId): array
    {
        $fromRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenantId)
            ->where('roles.name', RoleName::SalesManager->value)
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($fromRole !== []) {
            return $fromRole;
        }

        setPermissionsTeamId($tenantId);
        $salesMembers = $this->departments->departmentMemberIds($tenantId, Department::Sales->value);
        $managerIds = [];

        foreach ($salesMembers as $memberId) {
            $member = User::query()->find($memberId);
            if ($member && $this->permissions->isDepartmentManager($member, $tenantId)) {
                $managerIds[] = $memberId;
            }
        }

        return $managerIds;
    }

    /** @return array<int, int> */
    protected function salesRepIds(int $tenantId): array
    {
        $allSales = $this->departments->departmentMemberIds($tenantId, Department::Sales->value);

        $managerIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $tenantId)
            ->whereIn('roles.name', [RoleName::Owner->value, RoleName::SalesManager->value])
            ->pluck('model_has_roles.model_id')
            ->all();

        return array_values(array_diff($allSales, array_map('intval', $managerIds)));
    }

    protected function wonStageIds(): Collection
    {
        return PipelineStage::query()
            ->where('type', 'sales')
            ->where('is_won', true)
            ->pluck('id');
    }

    /** @param array<int, int> $userIds */
    protected function actualForUsers(array $userIds, Collection $wonStageIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === [] || $wonStageIds->isEmpty()) {
            return ['revenue' => 0.0, 'deals' => 0];
        }

        $query = Deal::query()
            ->whereIn('assigned_to', $userIds)
            ->whereIn('pipeline_stage_id', $wonStageIds)
            ->whereBetween('updated_at', [$from, $to]);

        return [
            'revenue' => (float) (clone $query)->sum('amount'),
            'deals' => (int) (clone $query)->count(),
        ];
    }

    /** @return array{from: Carbon, to: Carbon} */
    protected function periodBounds(int $jyear, int $jmonth): array
    {
        $start = Jalalian::fromFormat('Y/n/j', "{$jyear}/{$jmonth}/1")->toCarbon()->startOfDay();
        $days = (new Jalalian($jyear, $jmonth, 1))->getMonthDays();
        $end = Jalalian::fromFormat('Y/n/j', "{$jyear}/{$jmonth}/{$days}")->toCarbon()->endOfDay();

        return ['from' => $start, 'to' => $end];
    }

    protected function emptyHint(
        User $user,
        int $tenantId,
        array $rows,
        bool $canManageManagerTargets,
        bool $canManageUserTargets,
    ): ?string {
        if ($rows !== []) {
            return null;
        }

        if ($canManageManagerTargets) {
            return 'no_sales_manager';
        }

        if ($canManageUserTargets) {
            return 'no_sales_reps';
        }

        return 'no_accessible_rows';
    }

    protected function formatRow(
        ?SalesTarget $target,
        string $label,
        ?int $userId,
        array $actual,
        bool $canEdit,
        string $targetLevel,
    ): array {
        $revenueTarget = (float) ($target?->revenue_target ?? 0);
        $dealsTarget = $target?->deals_target;

        return [
            'id' => $target?->id,
            'scope' => SalesTarget::SCOPE_USER,
            'target_level' => $targetLevel,
            'label' => $label,
            'user_id' => $userId,
            'revenue_target' => $revenueTarget,
            'deals_target' => $dealsTarget,
            'notes' => $target?->notes,
            'actual_revenue' => $actual['revenue'],
            'actual_deals' => $actual['deals'],
            'revenue_progress' => $revenueTarget > 0
                ? round(min(100, ($actual['revenue'] / $revenueTarget) * 100), 1)
                : null,
            'deals_progress' => $dealsTarget > 0
                ? round(min(100, ($actual['deals'] / $dealsTarget) * 100), 1)
                : null,
            'can_edit' => $canEdit,
            'set_by' => $target?->setter?->name,
        ];
    }
}
