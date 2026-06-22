<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        PlatformAccess::rejectNonPlatformStaff($request->user());

        if (! $request->user()->is_active) {
            abort(403, 'حساب کاربری غیرفعال است.');
        }

        return $next($request);
    }
}
