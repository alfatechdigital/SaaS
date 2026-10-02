<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'name' => fake()->sentence(4),
            'client_name' => fake()->company(),
            'description' => fake()->paragraph(2),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'progress' => fake()->numberBetween(0, 100),
            'start_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'deadline' => fake()->dateTimeBetween('now', '+3 months'),
            'project_value' => fake()->numberBetween(10, 200) * 1_000_000,
            'pic_id' => null,
            'technologies' => fake()->randomElements(
                ['Laravel', 'Vue.js', 'React', 'Node.js', 'PostgreSQL', 'Docker', 'Tailwind CSS'],
                3,
            ),
            'notes' => fake()->sentence(),
        ];
    }
}
