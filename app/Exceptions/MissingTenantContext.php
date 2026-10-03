<?php

namespace App\Exceptions;

use App\Scopes\TeamScope;
use RuntimeException;

/**
 * Thrown when a tenant-owned model is queried without an active tenant.
 *
 * Failing loudly is deliberate (Fase 2, tugas 2.2.2): returning every tenant's
 * rows would be a data leak, and silently returning none would turn a real bug
 * into an empty screen. An exception names the model, so the cause is obvious
 * from the stack trace alone.
 *
 * @see TeamScope
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.2
 */
final class MissingTenantContext extends RuntimeException
{
    /**
     * Build the exception for the model that was queried.
     *
     * @param  class-string  $model
     */
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'Refusing to query [%s] without an active tenant. '
            .'Wrap the work in TenantContext::runFor(), or use withoutTeamScope() '
            .'if the query is intentionally cross-tenant.',
            $model,
        ));
    }
}
