<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Shared\Enums\Department;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use App\Support\DepartmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ChecksCrmAccess
{
    protected function crmUser(): User
    {
        return request()->user();
    }

    protected function crmTenantId(): int
    {
        return app(TenantContext::class)->tenantId();
    }

    protected function requirePermission(string $permission): void
    {
        $resolver = app(PermissionResolverService::class);

        if (! $resolver->hasPermission($this->crmUser(), $this->crmTenantId(), $permission)) {
            abort(403, 'شما به این بخش دسترسی ندارید');
        }
    }

    protected function scopeByDepartment(Builder $query, string $assignedColumn = 'assigned_to'): Builder
    {
        return app(DepartmentAccessService::class)
            ->scopeDepartmentRecords($query, $this->crmUser(), $this->crmTenantId(), $assignedColumn);
    }

    protected function assertCanViewRecord(Model $record, string $assignedColumn = 'assigned_to'): void
    {
        if (! app(DepartmentAccessService::class)->canViewRecord(
            $this->crmUser(),
            $this->crmTenantId(),
            $record,
            $assignedColumn,
        )) {
            abort(403, 'شما به این رکورد دسترسی ندارید');
        }
    }

    protected function defaultDepartment(string $pipelineType): string
    {
        return app(DepartmentAccessService::class)
            ->defaultDepartmentForPipeline($pipelineType)
            ?? Department::Sales->value;
    }

}
