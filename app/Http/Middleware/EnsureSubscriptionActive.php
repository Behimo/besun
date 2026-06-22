<?php

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = app(TenantContext::class)->tenantId();

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant not set.'], 403);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasActiveSubscription()) {
            return response()->json(['message' => 'Subscription inactive or expired.'], 402);
        }

        return $next($request);
    }
}
