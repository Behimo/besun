<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reports\ReportsService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Support\DepartmentAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected ReportsService $reports,
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
        protected DepartmentAccessService $departments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('reports.sales.read');

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : null;

        $data = $this->reports->build($from, $to);

        $user = $request->user();
        $tenantId = $this->tenantContext->tenantId();

        if ($this->permissions->isManagerRole($user, $tenantId)) {
            // Owner sees the whole tenant; department managers only their team.
            $scopeUserIds = null;

            if (! $this->permissions->isOwnerRole($user, $tenantId)) {
                $department = $this->permissions->departmentFor($user, $tenantId);
                $scopeUserIds = $department
                    ? $this->departments->departmentMemberIds($tenantId, $department)
                    : [];
            }

            $data['task_performance'] = $this->reports->taskPerformance($from, $to, $scopeUserIds);
            $data['employee_performance'] = $this->reports->employeePerformance($from, $to, $scopeUserIds);
        }

        return response()->json($data);
    }
}
