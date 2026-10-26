<?php

namespace Tests\Feature\Tenancy;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tugas 2.5.4 — tenant preferences: defaults are used, stored values win.
 *
 * Every assertion goes through `Team::setting()`, because that is the only
 * supported read path: nothing outside the model is allowed to know that
 * preferences live in one JSON column (tugas 2.5.2).
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.5.1 s/d 2.5.4
 */
class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_column_is_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('teams', 'settings'));

        $team = Team::factory()->create();

        $this->assertNull($team->settings);
    }

    public function test_the_configured_defaults_are_used_when_nothing_is_stored(): void
    {
        $team = Team::factory()->create();

        $this->assertSame('id', $team->setting('locale'));
        $this->assertSame('Asia/Jakarta', $team->setting('timezone'));
        $this->assertSame('IDR', $team->setting('currency'));
    }

    public function test_a_stored_value_wins_over_the_default(): void
    {
        $team = Team::factory()->create(['settings' => ['currency' => 'USD']]);

        $this->assertSame('USD', $team->setting('currency'));

        // Untouched keys keep falling back to the default.
        $this->assertSame('Asia/Jakarta', $team->setting('timezone'));
    }

    public function test_an_explicit_default_wins_over_the_configured_default(): void
    {
        $team = Team::factory()->create();

        $this->assertSame('en', $team->setting('locale', 'en'));
        $this->assertNull($team->setting('unknown_preference'));
    }

    public function test_the_defaults_come_from_config(): void
    {
        config(['tenancy.defaults.timezone' => 'Asia/Makassar']);

        $this->assertSame('Asia/Makassar', Team::factory()->create()->setting('timezone'));
    }

    public function test_a_setting_is_persisted_and_read_back(): void
    {
        $team = Team::factory()->create();

        $team->setSetting('locale', 'en');

        $this->assertSame('en', $team->setting('locale'));
        $this->assertSame('en', $team->fresh()?->setting('locale'));
        $this->assertSame(['locale' => 'en'], $team->fresh()?->settings);
    }

    public function test_settings_are_kept_per_tenant(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $first->setSetting('currency', 'USD');

        $this->assertSame('USD', $first->fresh()?->setting('currency'));
        $this->assertSame('IDR', $second->fresh()?->setting('currency'));
    }
}
