<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

/**
 * Fase 1 / tugas 1.4.2 — `config/tenancy.*` is a placeholder, not a feature.
 *
 * The keys exist so host-based tenant resolution can be added later without
 * renaming config, but nothing may depend on them yet. If a default ever stops
 * being `null`, some behaviour silently started reading a value that is not
 * decided (ADR-04 is deferred).
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.4.2
 */
class TenancyConfigTest extends TestCase
{
    public function test_host_resolution_keys_exist_with_null_defaults(): void
    {
        $this->assertIsArray(config('tenancy'));

        $this->assertArrayHasKey('base_domain', config('tenancy'));
        $this->assertArrayHasKey('subdomain_enabled', config('tenancy'));

        $this->assertNull(config('tenancy.base_domain'));
        $this->assertNull(config('tenancy.subdomain_enabled'));
    }
}
