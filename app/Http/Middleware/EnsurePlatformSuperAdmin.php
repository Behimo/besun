<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformAccess::isSuperAdmin($request->user())) {
            abort(403, 'فقط مدیر کل به این بخش دسترسی دارد.');
        }

        return $next($request);
    }
}
