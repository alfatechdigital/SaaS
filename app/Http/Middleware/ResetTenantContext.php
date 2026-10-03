<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the active tenant before every request.
 *
 * `TenantContext` is a singleton, and in long-lived runtimes (queue workers,
 * Octane, or simply a test that issues several requests in one process) the
 * container survives between requests. Without a reset, the tenant resolved by
 * request N-1 would still be active when request N runs — exactly the kind of
 * leak Fase 2 is meant to make impossible.
 *
 * Registered globally (prepended) so no route group can bypass it.
 *
 * @see TenantContext
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.4
 */
class ResetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->forget();

        return $next($request);
    }
}
