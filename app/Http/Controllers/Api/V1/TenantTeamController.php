<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use App\Infrastructure\Services\TenantTeamService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantTeamController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected TenantTeamService $teams,
    ) {}

    public function index(): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $teams = $this->teams->list($tenantId)
            ->map(fn ($team) => $this->teams->format($team));

        return response()->json(['teams' => $teams]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $team = $this->teams->create($tenantId, $data['name']);

        return response()->json(['team' => $this->teams->format($team)], 201);
    }

    public function update(Request $request, int $team): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $model = $this->teams->findForTenant($tenantId, $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $model = $this->teams->update($model, $data['name']);

        return response()->json(['team' => $this->teams->format($model)]);
    }

    public function destroy(int $team): JsonResponse
    {
        $this->assertOwner();
        $tenantId = $this->tenantContext->tenantId();

        $model = $this->teams->findForTenant($tenantId, $team);
        $this->teams->delete($model);

        return response()->json(['message' => 'Deleted.']);
    }

    protected function assertOwner(): Tenant
    {
        $user = request()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenantId = $this->tenantContext->tenantId();

        if (! $tenantId) {
            abort(403, 'Tenant access denied.');
        }

        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->isOwner($user)) {
            abort(403, 'فقط مالک مجموعه به این بخش دسترسی دارد');
        }

        return $tenant;
    }
}
