<?php

namespace Tests\Feature\Storage;

use App\Enums\TenantContextOrigin;
use App\Exceptions\MissingTenantContext;
use App\Models\Team;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fase 1 / tugas 1.3 — the tenant storage seam.
 *
 * Two properties matter here. Every path must start with `tenants/{team_id}/`,
 * so one tenant can never reach another tenant's file. And a path shaped like
 * `../../.env` must be rejected outright rather than normalised into something
 * that happens to work — path traversal has to fail loudly.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.3.1 s/d 1.3.4
 */
class TenantStorageTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    private TenantStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TenantStorage::DISK);

        $this->context = app(TenantContext::class);
        $this->storage = app(TenantStorage::class);
    }

    public function test_it_prefixes_the_path_with_the_active_tenant(): void
    {
        $team = Team::factory()->create();
        $this->context->set($team, TenantContextOrigin::Console);

        $this->assertSame("tenants/{$team->id}", $this->storage->directory());
        $this->assertSame("tenants/{$team->id}/portfolio", $this->storage->directory('portfolio'));
        $this->assertSame("tenants/{$team->id}/portfolio/hero.jpg", $this->storage->path('portfolio', 'hero.jpg'));
    }

    public function test_it_reads_and_writes_only_through_the_tenant_disk(): void
    {
        $team = Team::factory()->create();
        $this->context->set($team, TenantContextOrigin::Console);

        $this->storage->put('portfolio', 'hero.jpg', 'binary');

        Storage::disk(TenantStorage::DISK)->assertExists("tenants/{$team->id}/portfolio/hero.jpg");
        $this->assertTrue($this->storage->exists('portfolio', 'hero.jpg'));
        $this->assertSame('binary', $this->storage->get('portfolio', 'hero.jpg'));

        $this->storage->delete('portfolio', 'hero.jpg');

        $this->assertFalse($this->storage->exists('portfolio', 'hero.jpg'));
    }

    public function test_two_tenants_never_share_a_path(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $this->context->set($first, TenantContextOrigin::Console);
        $this->storage->put('portfolio', 'hero.jpg', 'milik pertama');

        $this->context->set($second, TenantContextOrigin::Console);

        $this->assertFalse($this->storage->exists('portfolio', 'hero.jpg'));
        $this->assertNotSame(
            "tenants/{$first->id}/portfolio/hero.jpg",
            $this->storage->path('portfolio', 'hero.jpg'),
        );
    }

    public function test_it_follows_the_tenant_inside_run_for(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $this->context->set($first, TenantContextOrigin::Console);

        $path = $this->context->runFor(
            $second,
            fn (Team $team): string => $this->storage->path('content', 'post.md'),
        );

        $this->assertSame("tenants/{$second->id}/content/post.md", $path);
        $this->assertSame("tenants/{$first->id}", $this->storage->directory());
    }

    public function test_it_refuses_to_work_without_an_active_tenant(): void
    {
        $this->expectException(MissingTenantContext::class);

        $this->storage->path('portfolio', 'hero.jpg');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeFilePaths(): array
    {
        return [
            'parent segment' => ['../../.env'],
            'nested parent' => ['portfolio/../../.env'],
            'sneaky parent' => ['portfolio/../other-tenant/hero.jpg'],
            'dot segment' => ['./hero.jpg'],
            'absolute unix path' => ['/etc/passwd'],
            'windows drive' => ['C:\\Windows\\win.ini'],
            'windows separator' => ['portfolio\\hero.jpg'],
            'null byte' => ["hero\0.jpg"],
            'empty segment' => ['portfolio//hero.jpg'],
            'empty file' => [''],
        ];
    }

    #[DataProvider('unsafeFilePaths')]
    public function test_it_rejects_paths_that_leave_the_tenant_folder(string $file): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Console);

        $this->expectException(InvalidArgumentException::class);

        $this->storage->path('portfolio', $file);
    }

    public function test_the_category_must_be_a_single_segment(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Console);

        $this->expectException(InvalidArgumentException::class);

        $this->storage->path('../other-tenant', 'hero.jpg');
    }
}
