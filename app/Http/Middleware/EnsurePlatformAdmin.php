<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformAccess::isAdminPortal($request->user())) {
            abort(403, 'دسترسی مدیریت پلتفرم ندارید.');
        }

        return $next($request);
    }
}
