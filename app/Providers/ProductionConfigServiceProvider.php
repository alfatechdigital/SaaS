<?php

namespace App\Providers;

use App\Exceptions\UnsafeProductionDatabase;
use Illuminate\Support\ServiceProvider;

/**
 * Refuses to boot production on configuration that only works for development.
 *
 * Registered in `bootstrap/providers.php`, so it runs on every boot — web
 * request, queue worker and console command alike. Failing at start-up with a
 * message that names the problem is much cheaper than discovering it from a
 * "database is locked" error minutes after launch.
 *
 * @see UnsafeProductionDatabase
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.4.3
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-13
 */
final class ProductionConfigServiceProvider extends ServiceProvider
{
    /**
     * Connections that may never serve production traffic.
     *
     * @var list<string>
     */
    private const DEV_ONLY_CONNECTIONS = ['sqlite'];

    /**
     * Bootstrap any production-only configuration checks.
     */
    public function boot(): void
    {
        $connection = (string) config('database.default');

        if (! $this->app->isProduction()) {
            return;
        }

        if (in_array($connection, self::DEV_ONLY_CONNECTIONS, true)) {
            throw UnsafeProductionDatabase::forConnection($connection);
        }
    }
}
