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
 * The rule only applies once an environment has actually been declared — a
 * `.env` file, or `APP_ENV` in the process environment. With neither, the
 * framework *labels* itself `production` while nothing has been configured at
 * all: that is the state of a fresh clone, where `composer install` boots the
 * application (`package:discover`) before `.env` exists. Refusing to boot there
 * would block every installation instead of catching a misconfigured deployment.
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

        if (! self::refusesToBoot(
            environment: $this->app->environment(),
            defaultConnection: $connection,
            environmentIsDeclared: $this->environmentIsDeclared(),
        )) {
            return;
        }

        throw UnsafeProductionDatabase::forConnection($connection);
    }

    /**
     * The rule itself, as a pure function so each branch can be tested directly.
     *
     * @param  string  $environment  the environment the application resolved to
     * @param  string  $defaultConnection  the value of `database.default`
     * @param  bool  $environmentIsDeclared  whether anything declared the environment
     */
    public static function refusesToBoot(
        string $environment,
        string $defaultConnection,
        bool $environmentIsDeclared,
    ): bool {
        if (! $environmentIsDeclared || $environment !== 'production') {
            return false;
        }

        return in_array($defaultConnection, self::DEV_ONLY_CONNECTIONS, true);
    }

    /**
     * Whether a `.env` file or an `APP_ENV` variable says which environment this is.
     */
    private function environmentIsDeclared(): bool
    {
        if (file_exists($this->app->environmentFilePath())) {
            return true;
        }

        return ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) !== null;
    }
}
