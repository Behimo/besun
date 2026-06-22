<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOperationalStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! PlatformAccess::isAdminPortal($user) && ! PlatformAccess::isSupportPortal($user)) {
            abort(403, 'دسترسی ندارید.');
        }

        return $next($request);
    }
}
