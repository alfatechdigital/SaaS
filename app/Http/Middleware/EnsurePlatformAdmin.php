<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform layer (cross-tenant actions such as creating a tenant).
 *
 * Deliberately separate from `TeamRole`: being an Owner of one tenant must not
 * grant platform rights. See PDR-03 and PDR-05.
 */
class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403);

        return $next($request);
    }
}
