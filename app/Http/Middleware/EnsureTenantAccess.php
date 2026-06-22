<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = app(\App\Infrastructure\Services\TenantContext::class)->tenantId();

        if (! $user || ! $tenantId || ! $user->belongsToTenant($tenantId)) {
            return response()->json(['message' => 'Tenant access denied.'], 403);
        }

        return $next($request);
    }
}
