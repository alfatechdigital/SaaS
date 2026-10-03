<?php

namespace Tests\Feature\Domain;

use App\Enums\TeamRole;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;

/**
 * P-1 — shared setup for the cross-tenant isolation suite.
 *
 * Extends {@see DomainTestCase} (one team: `$this->team`, `$this->owner`) with a
 * second, fully independent tenant: `$this->otherTeam` / `$this->otherOwner`.
 *
 * The point of this base class is {@see seedLookAlikeData()}: both tenants get
 * the *same* human-readable values (same company name, same project title, …).
 * Two tenants holding obviously different data would keep passing even when
 * isolation is broken — the identical data is what makes a leak visible, and
 * `team_id`/`id` is the only remaining discriminator.
 *
 * @see docs/syarhul-implementation-urgent.md P-1
 * @see docs/implementation/phase-01-fondasi.md tugas 1.2.1
 */
abstract class TenantIsolationTestCase extends DomainTestCase
{
    /**
     * The second tenant, deliberately unrelated to `$this->team`.
     */
    protected Team $otherTeam;

    /**
     * Owner of the second tenant.
     */
    protected User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otherTeam = Team::factory()->create();

        $this->otherOwner = User::factory()->create();
        $this->otherTeam->members()->attach($this->otherOwner, ['role' => TeamRole::Owner->value]);
        $this->otherOwner->switchTeam($this->otherTeam);
    }

    /**
     * Build a route URL for an arbitrary team.
     *
     * `DomainTestCase::teamRoute()` is pinned to `$this->team`, which is not
     * enough to prove isolation: the same route must be exercised for both
     * tenants.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function routeFor(Team $team, string $name, array $parameters = []): string
    {
        return route($name, ['current_team' => $team->slug, ...$parameters]);
    }

    /**
     * Seed one tenant with a fixed "look-alike" dataset.
     *
     * Every value below is hardcoded — never faked — so both tenants end up with
     * rows that are indistinguishable by name. When these values are asserted
     * against, the assertion can only pass because of `team_id` scoping.
     *
     * Creating the rows also produces one activity log per model
     * (`ActivityObserver`, ADR-10), so the activity-log module is covered too.
     *
     * @return array{
     *     company_profile: CompanyProfile,
     *     project: Project,
     *     task: Task,
     *     lead: Lead,
     *     content: ContentItem,
     *     transaction: Transaction,
     *     portfolio: PortfolioItem
     * }
     */
    protected function seedLookAlikeData(Team $team): array
    {
        $companyProfile = CompanyProfile::factory()->create([
            'team_id' => $team->id,
            'company_name' => 'PT Data Kembar',
            'email' => sprintf('kontak-%s@example.com', $team->id),
        ]);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'name' => 'Proyek Kembar',
            'client_name' => 'Klien Kembar',
        ]);

        $task = Task::factory()->create([
            'team_id' => $team->id,
            'project_id' => $project->id,
            'title' => 'Tugas Kembar',
        ]);

        $lead = Lead::factory()->create([
            'team_id' => $team->id,
            'company_name' => 'PT Prospek Kembar',
            'email' => sprintf('lead-%s@example.com', $team->id),
        ]);

        $content = ContentItem::factory()->create([
            'team_id' => $team->id,
            'title' => 'Konten Kembar',
        ]);

        $transaction = Transaction::factory()->income()->create([
            'team_id' => $team->id,
            'description' => 'Transaksi Kembar',
        ]);

        $portfolio = PortfolioItem::factory()->create([
            'team_id' => $team->id,
            'title' => 'Portfolio Kembar',
        ]);

        return [
            'company_profile' => $companyProfile,
            'project' => $project,
            'task' => $task,
            'lead' => $lead,
            'content' => $content,
            'transaction' => $transaction,
            'portfolio' => $portfolio,
        ];
    }
}
