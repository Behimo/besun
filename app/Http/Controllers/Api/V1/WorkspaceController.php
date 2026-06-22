<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tenant\CreateWorkspaceUseCase;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Infrastructure\Services\AuthPayloadService;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        protected CreateWorkspaceUseCase $createWorkspace,
        protected TenantContext $tenantContext,
        protected AuthPayloadService $authPayload,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();

        $workspaces = Workspace::where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json(['workspaces' => $workspaces]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->createWorkspace->execute(
            $request->user(),
            $this->tenantContext->tenantId(),
            $data['name'],
        );

        return response()->json(['workspace' => $workspace], 201);
    }

    public function switch(Request $request, Workspace $workspace): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();

        if ($workspace->tenant_id !== $tenantId || ! $request->user()->workspaces()->where('workspaces.id', $workspace->id)->exists()) {
            abort(403);
        }

        $request->user()->update(['current_workspace_id' => $workspace->id]);
        $this->tenantContext->set($tenantId, $workspace->id);

        return response()->json([
            'workspace' => $workspace,
            ...$this->authPayload->payload($request->user()->fresh()),
        ]);
    }
}
