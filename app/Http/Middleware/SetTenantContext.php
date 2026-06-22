<?php

namespace App\Http\Middleware;

use App\Infrastructure\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $tenantId = (int) ($request->header('X-Tenant-Id') ?: $user->current_tenant_id);

            if (! $tenantId) {
                $tenantId = (int) $user->tenants()->orderBy('tenant_user.created_at')->value('tenants.id');
            }

            $workspaceId = (int) ($request->header('X-Workspace-Id') ?: $user->current_workspace_id);

            if ($tenantId && $user->belongsToTenant($tenantId)) {
                if (! $workspaceId) {
                    $workspaceId = (int) DB::table('workspaces')
                        ->where('tenant_id', $tenantId)
                        ->orderByDesc('is_default')
                        ->value('id');
                }

                setPermissionsTeamId($tenantId);
                $this->tenantContext->set($tenantId, $workspaceId ?: null);
            }
        }

        return $next($request);
    }
}
