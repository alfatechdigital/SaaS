<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformAuditAction;
use App\Enums\TeamRole;
use App\Models\PlatformAuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase 2 / tugas 2.3 — tenant lifecycle.
 *
 * Suspension is a platform action that deletes nothing: the tenant shell and the
 * public page stop answering, and lifting the suspension restores everything.
 * The tests below cover the two access paths (shell, public) and the audit trail,
 * because "the button works" is not the same as "the tenant is actually blocked".
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.3.1 s/d 2.3.5
 */
class TenantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->platformAdmin()->create();

        $this->team = Team::factory()->create(['name' => 'Tenant Klien']);
        $this->owner = User::factory()->create();
        $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
        $this->owner->switchTeam($this->team);
    }

    public function test_platform_admin_can_suspend_and_activate_a_tenant(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.suspension', $this->team), ['suspended' => true])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertNotNull($this->team->fresh()?->suspended_at);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditAction::TenantSuspended->value,
            'target_id' => (string) $this->team->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.suspension', $this->team), ['suspended' => false])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertNull($this->team->fresh()?->suspended_at);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditAction::TenantActivated->value,
            'target_id' => (string) $this->team->id,
        ]);
        $this->assertSame(2, PlatformAuditLog::query()->count());
    }

    public function test_a_suspended_tenant_cannot_open_its_shell(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertForbidden()
            ->assertSee('disuspend');
    }

    public function test_activation_restores_access_to_the_shell(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.suspension', $this->team), ['suspended' => false]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertOk();
    }

    public function test_a_suspended_tenant_has_no_public_page(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->get(route('public.company-profile', ['team' => $this->team->slug]))
            ->assertNotFound();
    }

    public function test_a_suspended_tenant_does_not_accept_consultation_leads(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->post(route('public.consultation.store', ['team' => $this->team->slug]), [
            'contact_name' => 'Hendra Setiawan',
            'company_name' => 'PT Logistik Nusantara',
            'phone' => '0812-3456-7890',
            'email' => 'hendra@perusahaan.co.id',
            'service_type' => 'Custom ERP & Web Platform',
            'budget_estimate' => 35_000_000,
        ])->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_a_personal_team_cannot_be_suspended(): void
    {
        $personal = Team::factory()->personal()->create();

        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.suspension', $personal), ['suspended' => true])
            ->assertForbidden();

        $this->assertNull($personal->fresh()?->suspended_at);
        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    public function test_tenant_owners_cannot_suspend(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('platform.tenants.suspension', $this->team), ['suspended' => true])
            ->assertForbidden();

        $this->assertNull($this->team->fresh()?->suspended_at);
    }

    public function test_suspended_flag_is_required(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.suspension', $this->team), [])
            ->assertSessionHasErrors('suspended');

        $this->assertNull($this->team->fresh()?->suspended_at);
    }

    public function test_the_tenant_list_shows_the_suspension_state(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->actingAs($this->admin)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'tenants',
                    fn ($tenants) => collect($tenants)
                        ->firstWhere('id', $this->team->id)['suspended'] === true,
                ));
    }
}
