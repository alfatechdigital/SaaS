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
 * The provider is instantiated directly instead of booting a second application:
 * `boot()` is the exact code that runs on every real boot, so the guard is
 * exercised for real — without re-registering the model observers that
 * `AppServiceProvider` already attached.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.4.3
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-13
 */
class ProductionConfigGuardTest extends TestCase
{
    public function test_production_refuses_to_boot_on_sqlite(): void
    {
        $this->expectException(UnsafeProductionDatabase::class);
        $this->expectExceptionMessage('sqlite');

        $this->bootGuardAs('production', 'sqlite');
    }

    #[DataProvider('allowedConfigurations')]
    public function test_it_accepts_every_other_configuration(string $environment, string $connection): void
    {
        $this->bootGuardAs($environment, $connection);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allowedConfigurations(): array
    {
        return [
            'local keeps sqlite' => ['local', 'sqlite'],
            'testing keeps sqlite' => ['testing', 'sqlite'],
            'production on mysql' => ['production', 'mysql'],
            'production on pgsql' => ['production', 'pgsql'],
        ];
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
