<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
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
            'description' => fake()->sentence(10),
            'about' => fake()->paragraph(4),
            'services' => [
                [
                    'id' => 'srv-1',
                    'name' => fake()->words(3, true),
                    'desc' => fake()->sentence(12),
                    'icon' => 'database',
                ],
            ],
            'products' => [
                [
                    'id' => 'prod-1',
                    'name' => fake()->words(2, true),
                    'desc' => fake()->sentence(10),
                ],
            ],
            'contact' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'social_links' => [
                'website' => fake()->url(),
                'instagram' => 'https://instagram.com/'.fake()->userName(),
                'linkedin' => 'https://linkedin.com/company/'.fake()->userName(),
                'github' => 'https://github.com/'.fake()->userName(),
            ],
            'faq' => [
                [
                    'question' => fake()->sentence().'?',
                    'answer' => fake()->paragraph(2),
                ],
            ],
        ];
    }
}
