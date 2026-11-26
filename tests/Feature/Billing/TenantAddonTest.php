<?php

namespace Tests\Feature\Billing;

use App\Enums\Feature;
use App\Models\Team;
use App\Models\TenantAddon;
use App\Support\Billing\TenantAddons;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Add-ons are time-boxed, one row per feature, and never leak across tenants
 * (PDR-06, B-2 tugas 26 Nov).
 */
class TenantAddonTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_twice_extends_one_row_instead_of_stacking_two(): void
    {
        $team = Team::factory()->create();
        $addons = app(TenantAddons::class);

        $addons->grant($team, Feature::ProjectManagement);
        $addons->grant($team, Feature::Finance);

        $rows = $this->rowsOf($team);

        $this->assertCount(2, $rows);

        // Granting again with the same feature keeps a single row: the unique pair
        // (team, feature) is what stops two overlapping entitlements existing.
        $addons->grant($team, Feature::ProjectManagement);

        $this->assertCount(2, $this->rowsOf($team));
    }

    public function test_revoking_ends_the_add_on_without_deleting_the_row(): void
    {
        $team = Team::factory()->create();
        $addons = app(TenantAddons::class);

        $addons->grant($team, Feature::ProjectManagement);
        $addons->revoke($team, Feature::ProjectManagement);

        $row = TenantAddon::query()->withoutTeamScope()->where('team_id', $team->id)->sole();

        $this->assertNotNull($row->ends_at);
        $this->assertFalse($row->isActive());
        $this->assertSame([], $addons->active($team));
    }

    public function test_an_expired_add_on_stops_granting_its_feature(): void
    {
        $team = Team::factory()->create();

        app(TenantAddons::class)->grant(
            $team,
            Feature::Finance,
            CarbonImmutable::now()->subDay(),
        );

        $this->assertSame([], app(TenantAddons::class)->active($team));
    }

    public function test_add_ons_are_scoped_to_their_tenant(): void
    {
        $owner = Team::factory()->create();
        $other = Team::factory()->create();

        app(TenantAddons::class)->grant($owner, Feature::Finance);

        $this->assertSame([Feature::Finance], app(TenantAddons::class)->active($owner));
        $this->assertSame([], app(TenantAddons::class)->active($other));
    }

    /**
     * @return array<int, TenantAddon>
     */
    private function rowsOf(Team $team): array
    {
        return app(TenantContext::class)->runFor(
            $team,
            fn (): array => TenantAddon::query()->orderBy('id')->get()->all(),
        );
    }
}
