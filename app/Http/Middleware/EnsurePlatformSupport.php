<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSupport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformAccess::isSupportPortal($request->user())) {
            abort(403, 'دسترسی پشتیبانی پلتفرم ندارید.');
        }

        return $next($request);
    }
}
