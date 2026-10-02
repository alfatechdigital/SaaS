<?php

namespace Database\Factories;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Models\ContentItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
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
            'title' => fake()->sentence(5),
            'caption' => fake()->paragraph(2),
            'platform' => fake()->randomElement(ContentPlatform::cases()),
            'content_type' => fake()->randomElement([
                'Reels / Carousel',
                'Long-form Post + PDF Slide',
                'Short Video Vlog',
                'Infografis Carousel',
            ]),
            'media_url' => null,
            'status' => fake()->randomElement(ContentStatus::cases()),
            'scheduled_at' => fake()->randomElement([
                'Besok, 10:00 WIB',
                'Kamis, 14:00 WIB',
                'Jumat, 16:00 WIB',
                'Senin, 09:00 WIB',
            ]),
            'assignee_id' => null,
            'notes' => fake()->sentence(10),
        ];
    }
}
