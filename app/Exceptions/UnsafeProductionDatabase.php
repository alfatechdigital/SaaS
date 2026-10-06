<?php

namespace App\Exceptions;

use App\Providers\ProductionConfigServiceProvider;
use RuntimeException;

/**
 * Thrown when production is configured to run on a development-only database.
 *
 * SQLite locks the whole database file on every write, so the moment two tenants
 * write at the same time one of them fails with "database is locked". That is a
 * concurrency limit, not a size limit, and it is exactly what a multi-tenant
 * installation cannot afford — so the application refuses to start instead of
 * degrading quietly after launch.
 *
 * @see ProductionConfigServiceProvider
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-13
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.4.3
 */
final class UnsafeProductionDatabase extends RuntimeException
{
    /**
     * Build the exception for the connection production is misconfigured with.
     */
    public static function forConnection(string $connection): self
    {
        return new self(sprintf(
            'Refusing to start with APP_ENV=production on the [%s] connection. '
            .'SQLite is for local development and tests only (ADR-13); '
            .'point DB_CONNECTION at MySQL 8 or PostgreSQL instead.',
            $connection,
        ));
    }
}
