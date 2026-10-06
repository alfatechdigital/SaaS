<?php

namespace Database\Factories;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAuditLog>
 */
class PlatformAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => PlatformAuditAction::TenantCreated,
            'target_type' => (new Team)->getMorphClass(),
            'target_id' => fn (): string => (string) Team::factory()->create()->getKey(),
            'details' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * An entry left behind by an actor that no longer exists — the FK is
     * `nullOnDelete`, so deleting a user deletes the attribution, not the trail.
     */
    public function withoutActor(): static
    {
        return $this->state(fn (): array => ['actor_id' => null]);
    }
}
