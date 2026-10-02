<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
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
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('08##-####-####'),
            'email' => fake()->unique()->companyEmail(),
            'source' => fake()->randomElement([
                'Website Alfatech',
                'WhatsApp Inbound',
                'Instagram Ads',
                'Rekomendasi / Network',
            ]),
            'potential_project' => fake()->sentence(5),
            'estimated_value' => fake()->numberBetween(10, 100) * 1_000_000,
            'status' => fake()->randomElement(LeadStatus::cases()),
            'next_follow_up' => fake()->randomElement([
                'Hari ini',
                'Besok pagi',
                'WhatsApp Call Lanjutan',
                'Review Direksi Pekan Ini',
            ]),
            'notes' => fake()->sentence(12),
        ];
    }
}
