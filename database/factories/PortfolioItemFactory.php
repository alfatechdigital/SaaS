<?php

namespace Database\Factories;

use App\Models\PortfolioItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'team_id' => Team::factory(),
            'title' => $title,
            'client' => fake()->company(),
            'category' => fake()->randomElement([
                'Enterprise ERP',
                'Healthcare App',
                'Web & E-Commerce',
                'IoT & Dashboard',
            ]),
            'description' => fake()->paragraph(2),
            'technologies' => fake()->randomElements(
                ['Laravel', 'Vue.js', 'React', 'Next.js', 'Node.js', 'PostgreSQL', 'Docker'],
                3,
            ),
            'image_url' => 'https://images.unsplash.com/photo-'.fake()->numerify('##########'),
            'project_url' => fake()->url(),
            'completion_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'featured' => fake()->boolean(30),
            'published' => true,
        ];
    }

    /**
     * Indicate that the portfolio item is not yet published.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published' => false,
        ]);
    }

    /**
     * Indicate that the portfolio item is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
            'published' => true,
        ]);
    }
}
