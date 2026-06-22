<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Automation\AutomationService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\AutomationRule;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected AutomationService $automation,
        protected TenantContext $tenantContext,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->requirePermission('automation.read');

        return response()->json($this->automation->dashboard());
    }

    public function meta(Request $request): JsonResponse
    {
        $this->requirePermission('automation.read');

        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());

        return response()->json($this->automation->meta($tenant));
    }

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('automation.read');

        return response()->json([
            'rules' => $this->automation->listRules(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('automation.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_event' => ['required', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.params' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $rule = $this->automation->createRule($data);

            return response()->json(['rule' => $rule], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(AutomationRule $automationRule): JsonResponse
    {
        $this->requirePermission('automation.read');

        return response()->json(['rule' => $automationRule]);
    }

    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $this->requirePermission('automation.manage');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trigger_event' => ['sometimes', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', 'string'],
            'conditions.*.value' => ['nullable'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'actions.*.type' => ['required_with:actions', 'string'],
            'actions.*.params' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $rule = $this->automation->updateRule($automationRule, $data);

            return response()->json(['rule' => $rule]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $this->requirePermission('automation.manage');

        $this->automation->deleteRule($automationRule);

        return response()->json(['message' => 'Deleted.']);
    }

    public function toggle(AutomationRule $automationRule): JsonResponse
    {
        $this->requirePermission('automation.manage');

        $rule = $this->automation->toggleRule($automationRule);

        return response()->json(['rule' => $rule]);
    }

    public function runs(Request $request): JsonResponse
    {
        $this->requirePermission('automation.read');

        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:success,skipped,failed'],
        ]);

        $runs = $this->automation->listRuns(
            $data['page'] ?? 1,
            $data['status'] ?? null,
        );

        return response()->json($runs);
    }
}
