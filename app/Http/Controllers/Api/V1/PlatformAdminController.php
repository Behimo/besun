<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformAdminService;
use App\Application\Platform\PlatformAuditService;
use App\Application\Platform\PlatformReportsService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\MarketingLead;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    public function __construct(
        protected PlatformReportsService $reports,
        protected PlatformAdminService $admin,
        protected PlatformAuditService $audit,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'summary' => $this->reports->dashboardSummary(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;

        return response()->json($this->reports->buildReports($from, $to));
    }

    public function transactions(Request $request): JsonResponse
    {
        $paginator = $this->admin->listTransactions($request->query());

        return response()->json([
            'transactions' => collect($paginator->items())->map(
                fn ($tx) => $this->admin->formatTransaction($tx),
            ),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $paginator = $this->admin->listTenants($request->query());

        return response()->json([
            'tenants' => collect($paginator->items())->map(
                fn ($tenant) => $this->admin->formatTenantListItem($tenant),
            ),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    public function showTenant(Tenant $tenant): JsonResponse
    {
        return response()->json($this->admin->tenantDetail($tenant));
    }

    public function updateTenantStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $before = $tenant->status;
        $updated = $this->admin->updateTenantStatus($tenant, $data['status']);

        $this->audit->log(
            $request->user(),
            'tenant.status_updated',
            'tenant',
            $tenant->id,
            ['before' => $before, 'after' => $data['status']],
        );

        return response()->json([
            'message' => 'وضعیت مجموعه به‌روز شد.',
            'tenant' => $this->admin->formatTenantListItem($updated->load(['owner', 'subscription.modules'])),
        ]);
    }

    public function marketingLeads(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 50);

        $paginator = MarketingLead::query()
            ->when($request->query('q'), fn ($q, $search) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'leads' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    public function exportTransactions(Request $request): JsonResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;

        return response()->json([
            'rows' => $this->reports->exportTransactions($from, $to),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = $this->audit->list($request->query(), $perPage);

        return response()->json([
            'logs' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }
}
