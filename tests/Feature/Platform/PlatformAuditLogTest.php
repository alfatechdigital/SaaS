<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformAuditAction;
use App\Enums\TeamRole;
use App\Models\PlatformAuditLog;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * P-5 / Fase 2 tugas 2.4 — every installation-wide action leaves a trail.
 *
 * The assertions read `platform_audit_logs` directly, without activating a
 * tenant. That is half the point of the table: it is deliberately *not*
 * tenant-scoped (tugas 2.4.3), so an operator can read it while standing outside
 * every tenant — and so it survives the deletion of the tenant it describes.
 *
 * @see \docs\syarhul-implementation-urgent.md P-5
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.4
 */
class PlatformAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_tenant_is_recorded_with_the_operator_as_actor(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.tenants.store'), ['name' => 'Klien Baru'])
            ->assertRedirect(route('platform.tenants.index'));

        $team = Team::query()->where('name', 'Klien Baru')->sole();
        $entry = PlatformAuditLog::query()->sole();

        $this->assertSame(PlatformAuditAction::TenantCreated, $entry->action);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(Team::class, $entry->target_type);
        $this->assertSame((string) $team->id, $entry->target_id);
        $this->assertSame('Klien Baru', $entry->details['name']);
        $this->assertSame($admin->email, $entry->actor?->email);
    }

    public function test_deleting_a_tenant_keeps_the_name_it_was_known_by(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Klien Lama']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($admin)
            ->delete(route('platform.tenants.destroy', $team))
            ->assertRedirect(route('platform.tenants.index'));

        // The tenant row is soft-deleted, so the trail has to carry enough
        // context to stay meaningful on its own.
        $entry = PlatformAuditLog::query()->sole();

        $this->assertSame(PlatformAuditAction::TenantDeleted, $entry->action);
        $this->assertSame($team->id, (int) $entry->target_id);
        $this->assertSame('Klien Lama', $entry->details['name']);
    }

    public function test_both_directions_of_the_public_page_switch_are_recorded(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => false])
            ->assertRedirect(route('platform.tenants.index'));

        $this->actingAs($admin)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => true])
            ->assertRedirect(route('platform.tenants.index'));

        $entries = PlatformAuditLog::query()->orderBy('id')->get();

        $this->assertSame(
            [PlatformAuditAction::PublicPageDisabled, PlatformAuditAction::PublicPageEnabled],
            $entries->pluck('action')->all(),
        );
        $this->assertSame([false, true], $entries->pluck('details.enabled')->all());
    }

    public function test_actions_that_are_refused_leave_no_trace(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('platform.tenants.store'), ['name' => 'Sneaky Tenant'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => false])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('platform.tenants.destroy', $team))
            ->assertForbidden();

        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    public function test_the_trail_is_readable_without_an_active_tenant(): void
    {
        PlatformAuditLog::factory()->create();

        // A tenant-owned model would fail loudly here (TeamScope); the audit
        // trail must stay readable for an operator who is not inside any tenant.
        $this->assertNull(app(TenantContext::class)->team());
        $this->assertSame(1, PlatformAuditLog::query()->count());
    }

    public function test_the_command_lists_recent_entries(): void
    {
        PlatformAuditLog::factory()->create([
            'action' => PlatformAuditAction::TenantDeleted,
            'details' => ['name' => 'Klien Lama'],
        ]);

        $exitCode = Artisan::call('platform:audit-log');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('tenant.deleted', $output);
        $this->assertStringContainsString('Klien Lama', $output);
    }

    public function test_the_command_rejects_an_action_it_does_not_know(): void
    {
        $exitCode = Artisan::call('platform:audit-log', ['--action' => 'tenant.suspended']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Aksi tidak dikenal', Artisan::output());
    }

    public function test_the_command_marks_an_entry_whose_actor_is_gone(): void
    {
        PlatformAuditLog::factory()->withoutActor()->create([
            'action' => PlatformAuditAction::TenantCreated,
        ]);

        Artisan::call('platform:audit-log');

        $this->assertStringContainsString('(tidak diketahui)', Artisan::output());
    }
}
