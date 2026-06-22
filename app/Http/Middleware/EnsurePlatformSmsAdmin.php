<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSmsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformAccess::canManageSms($request->user())) {
            abort(403, 'دسترسی مدیریت پلتفرم پیامک ندارید.');
        }

        return $next($request);
    }
}
