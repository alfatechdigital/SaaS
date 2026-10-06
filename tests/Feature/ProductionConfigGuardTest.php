<?php

namespace Tests\Feature;

use App\Exceptions\UnsafeProductionDatabase;
use App\Providers\ProductionConfigServiceProvider;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P-3 / tugas 1.4.3 — production must not boot on a development-only database.
 *
 * `refusesToBoot()` is the rule as a pure function, so every branch is covered
 * directly — including the fresh-clone case, where nothing has declared an
 * environment yet and `composer install` boots the application (`package:discover`)
 * before `.env` exists. The last two tests drive the real `boot()` so the wiring
 * itself stays honest; the provider is instantiated directly there instead of
 * booting a second application, which would register `ActivityObserver` twice.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.4.3
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-13
 */
class ProductionConfigGuardTest extends TestCase
{
    public function test_the_rule_refuses_production_on_sqlite(): void
    {
        $this->assertTrue(
            ProductionConfigServiceProvider::refusesToBoot('production', 'sqlite', environmentIsDeclared: true),
        );
    }

    #[DataProvider('acceptedConfigurations')]
    public function test_the_rule_accepts_every_other_configuration(
        string $environment,
        string $connection,
        bool $environmentIsDeclared,
    ): void {
        $this->assertFalse(
            ProductionConfigServiceProvider::refusesToBoot($environment, $connection, $environmentIsDeclared),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function acceptedConfigurations(): array
    {
        return [
            // A fresh clone: no `.env`, no APP_ENV — the framework calls itself
            // production, but nothing has been configured yet.
            'nothing declared yet' => ['production', 'sqlite', false],
            'local keeps sqlite' => ['local', 'sqlite', true],
            'testing keeps sqlite' => ['testing', 'sqlite', true],
            'production on mysql' => ['production', 'mysql', true],
            'production on pgsql' => ['production', 'pgsql', true],
        ];
    }

    public function test_booting_production_on_sqlite_throws(): void
    {
        $this->expectException(UnsafeProductionDatabase::class);
        $this->expectExceptionMessage('sqlite');

        $this->bootGuardAs('production', 'sqlite');
    }

    public function test_booting_production_on_mysql_is_allowed(): void
    {
        $this->bootGuardAs('production', 'mysql');

        $this->expectNotToPerformAssertions();
    }

    /**
     * Boot the guard as if the application had started in the given environment.
     */
    private function bootGuardAs(string $environment, string $connection): void
    {
        Config::set('database.default', $connection);

        $this->app->detectEnvironment(fn (): string => $environment);

        (new ProductionConfigServiceProvider($this->app))->boot();
    }
}
