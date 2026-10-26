<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantContextOrigin;
use App\Exceptions\MissingTenantContext;
use App\Models\Team;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tugas 2.6.5 — cache keys of two tenants must never collide.
 *
 * The failure this guards against is silent: same logical key, one shared cache
 * entry, last writer wins. So each test uses the *same* key for two tenants and
 * proves the values stay apart.
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.6.1, 2.6.5
 */
class TenantCacheTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    private TenantCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->cache = app(TenantCache::class);
    }

    public function test_the_key_is_namespaced_with_the_active_tenant(): void
    {
        $team = Team::factory()->create();

        $this->context->set($team, TenantContextOrigin::Console);

        $this->assertSame('tenant:'.$team->id.':dashboard.stats', $this->cache->key('dashboard.stats'));
    }

    public function test_two_tenants_get_different_keys_for_the_same_name(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $firstKey = $this->context->runFor($first, fn (): string => $this->cache->key('dashboard.stats'));
        $secondKey = $this->context->runFor($second, fn (): string => $this->cache->key('dashboard.stats'));

        $this->assertNotSame($firstKey, $secondKey);
    }

    public function test_values_of_two_tenants_do_not_collide(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $this->context->runFor($first, function (): void {
            $this->cache->put('dashboard.stats', ['leads' => 3]);
        });

        $this->context->runFor($second, function (): void {
            $this->assertNull($this->cache->get('dashboard.stats'));

            $this->cache->put('dashboard.stats', ['leads' => 9]);
        });

        $this->context->runFor($first, function (): void {
            $this->assertSame(['leads' => 3], $this->cache->get('dashboard.stats'));
        });

        $this->context->runFor($second, function (): void {
            $this->assertSame(['leads' => 9], $this->cache->get('dashboard.stats'));
        });
    }

    public function test_the_entry_lands_in_the_application_cache_under_the_namespaced_key(): void
    {
        $team = Team::factory()->create();

        $this->context->runFor($team, function (): void {
            $this->cache->put('reports.summary', 'cached');
        });

        $this->assertSame('cached', Cache::get('tenant:'.$team->id.':reports.summary'));
        $this->assertNull(Cache::get('reports.summary'));
    }

    public function test_remember_caches_per_tenant(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $computations = 0;

        $value = function () use (&$computations): int {
            $computations++;

            return $computations;
        };

        $firstValue = $this->context->runFor($first, fn (): mixed => $this->cache->remember('counter', 60, $value));
        $secondValue = $this->context->runFor($second, fn (): mixed => $this->cache->remember('counter', 60, $value));

        $this->assertSame(1, $firstValue);
        $this->assertSame(2, $secondValue);
        $this->assertSame(2, $computations);

        $this->assertSame(1, $this->context->runFor($first, fn (): mixed => $this->cache->get('counter')));
    }

    public function test_forget_only_drops_the_active_tenants_entry(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $this->context->runFor($first, fn (): bool => $this->cache->put('counter', 'first'));
        $this->context->runFor($second, fn (): bool => $this->cache->put('counter', 'second'));

        $this->context->runFor($first, fn (): bool => $this->cache->forget('counter'));

        $this->assertNull($this->context->runFor($first, fn (): mixed => $this->cache->get('counter')));
        $this->assertSame('second', $this->context->runFor($second, fn (): mixed => $this->cache->get('counter')));
    }

    public function test_it_refuses_to_build_a_key_without_an_active_tenant(): void
    {
        $this->expectException(MissingTenantContext::class);

        $this->cache->key('dashboard.stats');
    }

    public function test_it_refuses_to_read_without_an_active_tenant(): void
    {
        $this->expectException(MissingTenantContext::class);

        $this->cache->get('dashboard.stats');
    }

    public function test_it_refuses_an_empty_key(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Console);

        $this->expectException(InvalidArgumentException::class);

        $this->cache->key('  ');
    }
}
