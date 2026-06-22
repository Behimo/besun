<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\User\PlatformUserProfileService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlatformUserController extends Controller
{
    public function __construct(
        protected PlatformUserProfileService $profiles,
        protected TenantContext $tenantContext,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $this->assertManagerOrOwner($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $tenant = $this->currentTenant();

        try {
            $result = $this->profiles->searchByPhone($data['phone'], $tenant);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertManagerOrOwner($request);

        $tenant = $this->currentTenant();

        return response()->json(
            $this->profiles->getProfile($user, $tenant),
        );
    }

    public function storeReview(Request $request, User $user): JsonResponse
    {
        $this->assertManagerOrOwner($request);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $tenant = $this->currentTenant();

        try {
            $review = $this->profiles->upsertReview(
                $tenant,
                $user,
                $request->user(),
                $data,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'review' => $review->load(['tenant', 'reviewer']),
            'profile' => $this->profiles->getProfile($user, $tenant),
        ]);
    }

    protected function assertManagerOrOwner(Request $request): void
    {
        $tenant = $this->currentTenant();

        if (! $tenant->isManagerOrOwner($request->user())) {
            abort(403, 'فقط مالک یا مدیر به این بخش دسترسی دارد');
        }
    }

    protected function currentTenant(): Tenant
    {
        return Tenant::findOrFail($this->tenantContext->tenantId());
    }
}
