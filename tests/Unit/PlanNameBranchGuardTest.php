<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps plan names out of branching code (Fase 4, tugas 4.3.5).
 *
 * `if ($team->plan === 'pro')` is impossible to change when the product moves a
 * feature between plans, and impossible to audit when it hides in a dozen files.
 * Callers must ask `$team->can(Feature::X)` instead, so this test fails the build
 * if a plan name or a `plan` property ever shows up in application code.
 *
 * Comment lines are skipped: documentation is allowed to *mention* the pattern it
 * forbids.
 */
class PlanNameBranchGuardTest extends TestCase
{
    /**
     * `->plan` as a property or method-less access, but not the `plan()` method.
     */
    private const PROPERTY_ACCESS = '/->\s*plan\b(?!\s*\()/';

    /**
     * A comparison against a plan identifier of any shape.
     */
    private const COMPARISON = '/\b(plan|planCode|plan_code|planName)\b\s*(===|!==|==|!=)/';

    public function test_application_code_never_branches_on_a_plan_name(): void
    {
        $this->assertSame([], $this->offendingLines(), implode("\n", [
            'Application code must not branch on a plan name.',
            'Ask $team->can(Feature::X) instead — see App\Support\Entitlements.',
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function offendingLines(): array
    {
        $base = dirname(__DIR__, 2);
        $roots = ['app', 'routes', 'resources/js'];
        $extensions = ['php', 'js', 'ts', 'vue'];
        $offenders = [];

        foreach ($roots as $root) {
            $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $root);

            if (! is_dir($path)) {
                continue;
            }

            /** @var \SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), $extensions, true)) {
                    continue;
                }

                $offenders = [...$offenders, ...$this->scan($file->getPathname(), $base)];
            }
        }

        return $offenders;
    }

    /**
     * @return array<int, string>
     */
    private function scan(string $file, string $base): array
    {
        $relative = str_replace('\\', '/', substr($file, strlen($base) + 1));
        $offenders = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            if ($this->isComment($line)) {
                continue;
            }

            if (preg_match(self::PROPERTY_ACCESS, $line) === 1 || preg_match(self::COMPARISON, $line) === 1) {
                $offenders[] = sprintf('%s:%d %s', $relative, $index + 1, trim($line));
            }
        }

        return $offenders;
    }

    private function isComment(string $line): bool
    {
        $trimmed = ltrim($line);

        foreach (['*', '//', '/*', '#', '<!--'] as $marker) {
            if (str_starts_with($trimmed, $marker)) {
                return true;
            }
        }

        return false;
    }
}
