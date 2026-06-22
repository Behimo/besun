<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Subscription\ActivatePlanUseCase;
use App\Application\Subscription\TenantModulePurchaseService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\AuthPayloadService;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        protected ActivatePlanUseCase $activatePlan,
        protected TenantModulePurchaseService $modulePurchase,
        protected TenantContext $tenantContext,
        protected AuthPayloadService $authPayload,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::with(['subscription.plan', 'subscription.modules'])
            ->findOrFail($this->tenantContext->tenantId());

        return response()->json([
            'tenant' => $this->authPayload->formatTenant($tenant),
            'subscription' => $tenant->subscription,
            'is_active' => $tenant->hasActiveCoreModule(),
            'has_core_module' => $tenant->hasActiveCoreModule(),
            'active_modules' => $tenant->activeModuleSlugs(),
            'trial_ends_at' => $tenant->trial_ends_at,
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'module_ids' => ['array'],
            'module_ids.*' => ['exists:plan_modules,id'],
        ]);

        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());
        $this->assertOwner($request, $tenant);

        $subscription = $this->activatePlan->execute(
            $tenant,
            $data['plan_id'],
            $data['module_ids'] ?? [],
        );

        return response()->json(['subscription' => $subscription]);
    }

    public function addModules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_ids' => ['required', 'array'],
            'module_ids.*' => ['exists:plan_modules,id'],
        ]);

        $tenant = Tenant::with('subscription')->findOrFail($this->tenantContext->tenantId());
        $this->assertOwner($request, $tenant);

        $subscription = $this->activatePlan->addModules($tenant->subscription, $data['module_ids']);

        return response()->json(['subscription' => $subscription]);
    }

    public function purchaseModules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_id' => ['required', 'exists:plan_modules,id'],
            'modules.*.period' => ['required', 'in:monthly,semi_annual,annual'],
            'seat_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());
        $this->assertOwner($request, $tenant);

        try {
            $subscription = $this->modulePurchase->purchase(
                $tenant,
                $data['modules'],
                $data['seat_count'] ?? null,
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'subscription' => $subscription,
            ...$this->authPayload->payload($request->user()->fresh()),
        ]);
    }

    public function previewModule(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_id' => ['required', 'exists:plan_modules,id'],
            'modules.*.period' => ['required', 'in:monthly,semi_annual,annual'],
            'seat_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());
        $module = PlanModule::where('slug', $slug)->firstOrFail();

        try {
            $preview = $this->modulePurchase->preview(
                $tenant,
                $data['modules'],
                $data['seat_count'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'module' => $module,
            'preview' => $preview,
        ]);
    }

    protected function assertOwner(Request $request, Tenant $tenant): void
    {
        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه می‌تواند اشتراک را مدیریت کند.');
        }

        setPermissionsTeamId($tenant->id);
    }
}
