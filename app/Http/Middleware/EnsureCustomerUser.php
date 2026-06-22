<?php

namespace App\Http\Middleware;

use App\Support\PlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerUser
{
    public function handle(Request $request, Closure $next): Response
    {
        PlatformAccess::rejectCustomerUser($request->user());

        return $next($request);
    }
}
