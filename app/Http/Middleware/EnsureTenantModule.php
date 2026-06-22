<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModule
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if (! $tenantId) {
            return response()->json(['message' => 'مجموعه انتخاب نشده است.'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasModule($slug)) {
            return response()->json([
                'message' => 'این ماژول برای مجموعه فعال نیست.',
                'code' => 'module_required',
                'module_slug' => $slug,
            ], 402);
        }

        return $next($request);
    }
}
