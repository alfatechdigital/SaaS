<?php

namespace App\Support;

use App\Exceptions\MissingTenantContext;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;

/**
 * The only supported way to read or write tenant-scoped cache entries.
 *
 * The problem this solves is silent: two tenants that happen to use the same
 * logical key (`settings`, `dashboard.stats`, …) would share one cache entry, and
 * whoever cached last wins. Prefixing every key with `tenant:{id}:` removes the
 * collision, and taking that id from the active {@see TenantContext} removes the
 * possibility of a caller prefixing with the wrong tenant.
 *
 * Fails loudly without an active tenant instead of writing a global entry that
 * would then be served to every tenant.
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.6.1
 */
final class TenantCache
{
    /**
     * Every tenant key starts with this segment.
     */
    private const PREFIX = 'tenant';

    public function __construct(
        private readonly TenantContext $context,
        private readonly Repository $cache,
    ) {}

    /**
     * The namespaced cache key for the active tenant.
     *
     * Example: `tenant:7:dashboard.stats`.
     */
    public function key(string $key): string
    {
        return sprintf('%s:%d:%s', self::PREFIX, $this->teamId(), $this->suffix($key));
    }

    /**
     * Read an entry of the active tenant, or the given default when absent.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($key), $default);
    }

    /**
     * Write an entry for the active tenant.
     */
    public function put(string $key, mixed $value, DateInterval|DateTimeInterface|int|null $ttl = null): bool
    {
        return $this->cache->put($this->key($key), $value, $ttl);
    }

    /**
     * Read an entry of the active tenant, computing and storing it when absent.
     *
     * @param  Closure(): mixed  $callback
     */
    public function remember(string $key, DateInterval|DateTimeInterface|int|null $ttl, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($key), $ttl, $callback);
    }

    /**
     * Forget an entry of the active tenant.
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($this->key($key));
    }

    /**
     * Primary key of the active tenant.
     */
    private function teamId(): int
    {
        $team = $this->context->team();

        if ($team === null) {
            throw MissingTenantContext::forCache();
        }

        return (int) $team->getKey();
    }

    /**
     * Keys are caller-chosen suffixes; an empty one would namespace nothing.
     */
    private function suffix(string $key): string
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('A tenant cache key must not be empty.');
        }

        return $key;
    }
}
