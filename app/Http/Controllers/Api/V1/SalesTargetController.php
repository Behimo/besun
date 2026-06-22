<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\SalesTarget\SalesTargetService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesTargetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTargetController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected SalesTargetService $targets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('reports.sales.read');

        $data = $request->validate([
            'jyear' => ['required', 'integer', 'min:1300', 'max:1500'],
            'jmonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json(
            $this->targets->list($request->user(), $this->crmTenantId(), (int) $data['jyear'], (int) $data['jmonth']),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('reports.sales.read');

        $data = $request->validate([
            'scope' => ['required', 'in:department,user'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'jyear' => ['required', 'integer', 'min:1300', 'max:1500'],
            'jmonth' => ['required', 'integer', 'min:1', 'max:12'],
            'revenue_target' => ['required', 'numeric', 'min:0'],
            'deals_target' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = $this->targets->upsert($request->user(), $this->crmTenantId(), $data);

        return response()->json([
            'target' => new SalesTargetResource($target),
        ]);
    }
}
