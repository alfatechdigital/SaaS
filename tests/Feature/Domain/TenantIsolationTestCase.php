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

    /**
     * A valid `projects.update` payload.
     *
     * The cross-tenant tests send a payload that passes validation on purpose:
     * otherwise the request would stop at a 302 + validation errors and the
     * expected 404 would never be reached, quietly turning the assertion into a
     * false negative.
     *
     * @return array<string, mixed>
     */
    protected function projectPayload(): array
    {
        return [
            'name' => 'Proyek Diubah',
            'client_name' => 'Klien Diubah',
            'description' => 'Deskripsi proyek',
            'status' => 'development',
            'progress' => 25,
            'start_date' => '2026-01-01',
            'deadline' => '2026-06-30',
            'project_value' => 10_000_000,
            'pic_id' => $this->member->id,
            'technologies' => ['Laravel'],
            'notes' => 'Catatan',
        ];
    }

    /**
     * A valid `tasks.update` payload for the **acting** team's project.
     *
     * `project_id` must belong to the acting tenant: the rule is scoped with
     * `where('team_id', $teamId)`, so pointing it at the other tenant's project
     * would fail validation and mask the 404.
     *
     * @return array<string, mixed>
     */
    protected function taskPayload(Project $project): array
    {
        return [
            'project_id' => $project->id,
            'title' => 'Tugas Diubah',
            'description' => 'Deskripsi tugas',
            'assignee_id' => $this->member->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => '2026-02-01',
        ];
    }

    /**
     * A valid `leads.update` payload.
     *
     * @return array<string, mixed>
     */
    protected function leadPayload(): array
    {
        return [
            'company_name' => 'PT Prospek Diubah',
            'contact_name' => 'Kontak Diubah',
            'phone' => '0812-0000-0000',
            'email' => null,
            'source' => 'Website Alfatech',
            'potential_project' => 'Sistem Kembar',
            'estimated_value' => 15_000_000,
            'status' => 'new',
            'next_follow_up' => 'Besok pagi',
            'notes' => 'Catatan',
        ];
    }

    /**
     * A valid `contents.update` payload.
     *
     * @return array<string, mixed>
     */
    protected function contentPayload(): array
    {
        return [
            'title' => 'Konten Diubah',
            'caption' => 'Caption konten',
            'platform' => 'instagram',
            'content_type' => 'Reels / Carousel',
            'media_url' => null,
            'status' => 'draft',
            'scheduled_at' => 'Besok, 10:00 WIB',
            'assignee_id' => $this->member->id,
            'notes' => 'Catatan',
        ];
    }

    /**
     * A valid `transactions.update` payload.
     *
     * @return array<string, mixed>
     */
    protected function transactionPayload(): array
    {
        return [
            'category' => 'project_income',
            'project_id' => null,
            'description' => 'Transaksi Diubah',
            'amount' => 1_500_000,
            'date' => '2026-01-15',
        ];
    }

    /**
     * A valid `portfolio.update` payload.
     *
     * @return array<string, mixed>
     */
    protected function portfolioPayload(): array
    {
        return [
            'title' => 'Portfolio Diubah',
            'client' => 'Klien Diubah',
            'category' => 'Web & E-Commerce',
            'description' => 'Deskripsi portfolio',
            'technologies' => ['Vue.js'],
            'image_url' => 'https://example.com/image.jpg',
            'project_url' => 'https://example.com',
            'completion_date' => '2026-01-10',
            'featured' => true,
            'published' => true,
        ];
    }
}
