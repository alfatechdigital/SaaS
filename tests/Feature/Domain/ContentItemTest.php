<?php

namespace Tests\Feature\Domain;

use App\Models\ContentItem;

class ContentItemTest extends DomainTestCase
{
    public function test_owner_can_create_a_content_item(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('contents.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('content_items', [
            'team_id' => $this->team->id,
            'title' => 'Konten Baru',
            'platform' => 'instagram',
            'status' => 'draft',
        ]);
    }

    public function test_owner_can_update_a_content_item(): void
    {
        $content = ContentItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('contents.update', ['content' => $content->id]), $this->payload(['status' => 'published']))
            ->assertRedirect();

        $this->assertSame('published', $content->fresh()?->status->value);
    }

    public function test_owner_can_delete_a_content_item(): void
    {
        $content = ContentItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('contents.destroy', ['content' => $content->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('content_items', ['id' => $content->id]);
    }

    public function test_platform_must_be_a_known_value(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('contents.store'), $this->payload(['platform' => 'myspace']))
            ->assertSessionHasErrors('platform');
    }

    public function test_media_url_must_use_http_or_https(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('contents.store'), $this->payload(['media_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('media_url');

        $this->actingAs($this->owner)
            ->post($this->teamRoute('contents.store'), $this->payload(['media_url' => 'https://example.com/media.jpg']))
            ->assertSessionDoesntHaveErrors('media_url');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Konten Baru',
            'caption' => 'Caption konten',
            'platform' => 'instagram',
            'content_type' => 'Reels / Carousel',
            'media_url' => null,
            'status' => 'draft',
            'scheduled_at' => 'Besok, 10:00 WIB',
            'assignee_id' => $this->member->id,
            'notes' => 'Catatan',
            ...$overrides,
        ];
    }
}
