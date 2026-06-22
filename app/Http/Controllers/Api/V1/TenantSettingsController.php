<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\AuthPayloadService;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    public function __construct(
        protected AuthPayloadService $authPayload,
        protected TenantContext $tenantContext,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = $this->ownerTenant($request);

        $tenant->load(['subscription.plan', 'subscription.modules', 'workspaces']);

        return response()->json([
            'tenant' => $this->authPayload->formatTenant($tenant),
            'workspace' => $tenant->workspaces()->where('is_default', true)->first()
                ?? $tenant->workspaces()->first(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = $this->ownerTenant($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant->update(['name' => $data['name']]);

        return response()->json([
            'tenant' => $this->authPayload->formatTenant($tenant->fresh(['subscription.plan', 'subscription.modules'])),
            'message' => 'تنظیمات مجموعه ذخیره شد.',
        ]);
    }

    protected function ownerTenant(Request $request): Tenant
    {
        $tenant = Tenant::with(['subscription.plan', 'subscription.modules'])
            ->findOrFail($this->tenantContext->tenantId());

        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه به تنظیمات دسترسی دارد.');
        }

        return $tenant;
    }
}
