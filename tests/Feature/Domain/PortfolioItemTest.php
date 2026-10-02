<?php

namespace Tests\Feature\Domain;

use App\Models\PortfolioItem;

class PortfolioItemTest extends DomainTestCase
{
    public function test_owner_can_create_a_portfolio_item(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('portfolio.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('portfolio_items', [
            'team_id' => $this->team->id,
            'title' => 'Portfolio Baru',
            'featured' => true,
            'published' => true,
        ]);
    }

    public function test_owner_can_update_a_portfolio_item(): void
    {
        $item = PortfolioItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('portfolio.update', ['portfolioItem' => $item->id]), $this->payload(['published' => false]))
            ->assertRedirect();

        $this->assertFalse($item->fresh()?->published);
    }

    public function test_owner_can_delete_a_portfolio_item(): void
    {
        $item = PortfolioItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('portfolio.destroy', ['portfolioItem' => $item->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('portfolio_items', ['id' => $item->id]);
    }

    public function test_technologies_are_stored_as_an_array(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('portfolio.store'), $this->payload(['technologies' => ['Vue.js', 'Laravel']]))
            ->assertSessionDoesntHaveErrors();

        $item = PortfolioItem::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame(['Vue.js', 'Laravel'], $item->technologies);
    }

    public function test_title_is_required(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('portfolio.store'), $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Portfolio Baru',
            'client' => 'PT Klien',
            'category' => 'Web & E-Commerce',
            'description' => 'Deskripsi portfolio',
            'technologies' => ['Vue.js'],
            'image_url' => 'https://example.com/image.jpg',
            'project_url' => 'https://example.com',
            'completion_date' => '2025-01-10',
            'featured' => true,
            'published' => true,
            ...$overrides,
        ];
    }
}
