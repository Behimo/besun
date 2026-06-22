<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantCoreActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if (! $tenantId) {
            return response()->json(['message' => 'مجموعه انتخاب نشده است.'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasActiveCoreModule()) {
            return response()->json([
                'message' => 'ماژول پایه مجموعه فعال نیست.',
                'code' => 'core_module_required',
                'redirect' => 'apps-tenant-modules',
                'module_slug' => 'core-base',
            ], 402);
        }

        return $next($request);
    }
}
