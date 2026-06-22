<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Subscription\TenantModulePurchaseService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\AuthPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected TenantModulePurchaseService $modulePurchase,
        protected AuthPayloadService $authPayload,
    ) {}

    public function preview(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertOwner($request, $tenant);

        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_id' => ['required', 'exists:plan_modules,id'],
            'modules.*.period' => ['required', 'in:monthly,semi_annual,annual'],
            'seat_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
            'tenant' => $this->authPayload->formatTenant($tenant->fresh(['subscription.plan', 'subscription.modules'])),
            'preview' => $this->formatPreview($preview),
        ]);
    }

    public function purchase(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertOwner($request, $tenant);

        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.module_id' => ['required', 'exists:plan_modules,id'],
            'modules.*.period' => ['required', 'in:monthly,semi_annual,annual'],
            'seat_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
            'tenant' => $this->authPayload->formatTenant($tenant->fresh(['subscription.plan', 'subscription.modules'])),
            ...$this->authPayload->payload($request->user()->fresh()),
        ]);
    }

    protected function assertOwner(Request $request, Tenant $tenant): void
    {
        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه می‌تواند اشتراک را مدیریت کند.');
        }
    }

    private function formatPreview(array $preview): array
    {
        if (isset($preview['core_expires_at']) && $preview['core_expires_at'] instanceof \DateTimeInterface) {
            $preview['core_expires_at'] = $preview['core_expires_at']->format('c');
        }

        return $preview;
    }
}
