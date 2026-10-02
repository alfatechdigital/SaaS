<?php

namespace Database\Factories;

use App\Enums\ActivityEntityType;
use App\Models\ActivityLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'action' => fake()->sentence(4),
            'entity_type' => fake()->randomElement(ActivityEntityType::cases()),
            'entity_id' => null,
            'details' => fake()->paragraph(2),
            'performed_by_id' => null,
        ];
    }
}
