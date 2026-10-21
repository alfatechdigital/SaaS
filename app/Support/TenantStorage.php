<?php

namespace App\Support;

use App\Exceptions\MissingTenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;

/**
 * The only supported way to read or write tenant files.
 *
 * Callers never build a path themselves: every path is prefixed with
 * `tenants/{team_id}/`, taken from the active {@see TenantContext}. That removes
 * two whole classes of bug — one tenant reading another tenant's file, and a
 * filename like `../../.env` escaping the tenant folder.
 *
 * The disk is a seam, not a feature (ADR-05): there is no upload UI yet, and
 * moving to S3 later means changing the `tenants` disk configuration only. This
 * class therefore never touches `storage_path()`; it always goes through
 * {@see FilesystemManager}.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.3.1 s/d 1.3.4
 */
final class TenantStorage
{
    /**
     * Name of the disk in `config/filesystems.php`.
     */
    public const DISK = 'tenants';

    /**
     * Every tenant path starts with this segment.
     */
    private const PREFIX = 'tenants';

    public function __construct(
        private readonly TenantContext $context,
        private readonly FilesystemManager $filesystems,
    ) {}

    /**
     * The tenant disk itself, rooted per `config/filesystems.php`.
     */
    public function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }

    /**
     * Folder that holds the active tenant's files, optionally for one category.
     *
     * Example: `tenants/7` or `tenants/7/portfolio`.
     */
    public function directory(?string $category = null): string
    {
        $directory = self::PREFIX.'/'.$this->teamId();

        return $category === null
            ? $directory
            : $directory.'/'.$this->category($category);
    }

    /**
     * Full disk path for a tenant file.
     *
     * Example: `tenants/7/portfolio/hero.jpg`.
     *
     * @throws InvalidArgumentException when the path would leave the tenant folder
     */
    public function path(string $category, string $file): string
    {
        $directory = $this->directory($category);
        $path = $directory.'/'.$this->relativePath($file);

        if (! str_starts_with($path, $directory.'/')) {
            throw new InvalidArgumentException(sprintf(
                'Tenant storage path [%s] escapes [%s].', $path, $directory,
            ));
        }

        return $path;
    }

    /**
     * Write a file for the active tenant.
     */
    public function put(string $category, string $file, string $contents): bool
    {
        return $this->disk()->put($this->path($category, $file), $contents);
    }

    /**
     * Read a file of the active tenant, or `null` when it does not exist.
     */
    public function get(string $category, string $file): ?string
    {
        return $this->disk()->get($this->path($category, $file));
    }

    /**
     * Whether the active tenant has that file.
     */
    public function exists(string $category, string $file): bool
    {
        return $this->disk()->exists($this->path($category, $file));
    }

    /**
     * Delete a file of the active tenant.
     */
    public function delete(string $category, string $file): bool
    {
        return $this->disk()->delete($this->path($category, $file));
    }

    /**
     * Primary key of the active tenant.
     *
     * Fails loudly instead of picking a default: writing without a tenant would
     * put the file in a folder nobody owns.
     */
    private function teamId(): int
    {
        $team = $this->context->team();

        if ($team === null) {
            throw MissingTenantContext::forStorage();
        }

        return (int) $team->getKey();
    }

    /**
     * Categories are a single segment (`portfolio`, `company-profile`, …).
     */
    private function category(string $category): string
    {
        if (str_contains($category, '/') || $category === '.' || $category === '..') {
            throw new InvalidArgumentException(sprintf(
                'Tenant storage category [%s] must be a single path segment.', $category,
            ));
        }

        return $this->assertNoTraversal($category, 'category');
    }

    /**
     * Normalise a relative file path, rejecting anything that walks upwards.
     */
    private function relativePath(string $file): string
    {
        $this->assertNoTraversal($file, 'file');

        $segments = explode('/', $file);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException(sprintf(
                    'Tenant storage file [%s] must not contain empty, "." or ".." segments.', $file,
                ));
            }
        }

        return implode('/', $segments);
    }

    /**
     * Reject absolute paths, Windows separators, and null bytes.
     */
    private function assertNoTraversal(string $value, string $label): string
    {
        if ($value === '' || str_contains($value, "\0")) {
            throw new InvalidArgumentException(sprintf('Tenant storage %s must not be empty.', $label));
        }

        if (str_contains($value, '\\')) {
            throw new InvalidArgumentException(sprintf(
                'Tenant storage %s [%s] must use "/" as separator.', $label, $value,
            ));
        }

        if (str_starts_with($value, '/') || preg_match('/^[A-Za-z]:/', $value) === 1) {
            throw new InvalidArgumentException(sprintf(
                'Tenant storage %s [%s] must be relative.', $label, $value,
            ));
        }

        return $value;
    }
}
