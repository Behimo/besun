<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Bi\BiReportTemplates;
use App\Application\Bi\BiService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected BiService $bi,
        protected BiReportTemplates $templates,
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeBiAccess($request);

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : null;
        $granularity = $request->input('granularity', 'month');

        return response()->json($this->bi->buildDashboard($from, $to, $granularity));
    }

    public function templateList(Request $request): JsonResponse
    {
        $this->authorizeBiAccess($request);

        return response()->json(['templates' => $this->templates->list()]);
    }

    public function report(Request $request): JsonResponse
    {
        $this->authorizeBiAccess($request);

        $request->validate([
            'template' => 'required|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'department' => 'nullable|in:sales,marketing,finance',
            'assignee_id' => 'nullable|integer',
        ]);

        $filters = $request->only(['from', 'to', 'department', 'assignee_id']);

        return response()->json(
            $this->templates->build($request->input('template'), $filters),
        );
    }

    protected function authorizeBiAccess(Request $request): void
    {
        if (! $this->permissions->isManagerRole($request->user(), $this->tenantContext->tenantId())) {
            abort(403, 'دسترسی BI فقط برای مدیران و مالک مجموعه است.');
        }
    }
}
