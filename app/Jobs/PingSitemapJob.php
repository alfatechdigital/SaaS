<?php

namespace App\Jobs;

use App\Models\Team;
use App\Observers\PublicSeoObserver;
use App\Support\PublicSitemap;
use App\Support\TenantCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tell search engines that a tenant's sitemap changed (B-8, SEO automation).
 *
 * The job is the second half of the automation: {@see PublicSeoObserver}
 * notices that something public changed, and this job decides whether that change
 * is worth telling anyone about.
 *
 * Three refusals keep it honest:
 *
 * 1. **Hidden tenants are never pinged** — moderation and suspension must not
 *    advertise a page that answers 404.
 * 2. **Unchanged documents are not pinged** — the digest of the rendered sitemap
 *    is stored per tenant in {@see TenantCache}, so saving the same form twice
 *    does not produce two pings.
 * 3. **No endpoint, no request** — the engine list is deployment configuration
 *    (`config/seo.php`), empty by default.
 *
 * A failed ping is logged rather than rethrown: the product works without it, and
 * a retry storm against an engine that rejects us helps nobody.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md
 */
final class PingSitemapJob extends TenantJob
{
    /**
     * Where {@see TenantCache} keeps the digest of the last pinged sitemap.
     */
    private const FINGERPRINT_KEY = 'seo.sitemap.pinged';

    /**
     * Endpoints resolved for this run.
     *
     * @var list<string>
     */
    private array $urls = [];

    /**
     * Whether this job should do anything at all for the given tenant.
     */
    protected function handleForTenant(Team $team): void
    {
        if (! config('seo.ping.enabled')) {
            return;
        }

        $this->urls = $this->configuredUrls();

        if ($this->urls === []) {
            return;
        }

        if (! $team->isPublicPageVisible()) {
            Log::info('Skipping a sitemap ping for a tenant that is not publicly visible.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return;
        }

        $sitemap = PublicSitemap::for($team);
        $fingerprint = $sitemap->fingerprint();

        $cache = app(TenantCache::class);

        if ($cache->get(self::FINGERPRINT_KEY) === $fingerprint) {
            return;
        }

        $sitemapUrl = route('public.sitemap', ['team' => $team->slug]);

        // The digest is only remembered when an endpoint accepted the ping. A
        // network blip or a temporarily failing endpoint must not silence the next
        // change; retrying on the next edit is cheap, and the alternative is never
        // telling engines again.
        if ($this->ping($team, $sitemapUrl)) {
            $cache->put(self::FINGERPRINT_KEY, $fingerprint);
        }
    }

    /**
     * @return list<string>
     */
    private function configuredUrls(): array
    {
        $urls = config('seo.ping.urls', []);

        return is_array($urls) ? array_values(array_filter($urls, 'is_string')) : [];
    }

    /**
     * Send the sitemap URL to every configured endpoint.
     *
     * @return bool Whether at least one endpoint accepted the ping.
     */
    private function ping(Team $team, string $sitemapUrl): bool
    {
        $target = rawurlencode($sitemapUrl);
        $delivered = false;

        foreach ($this->urls as $template) {
            $url = str_replace('{sitemap}', $target, $template);

            try {
                $response = Http::timeout((int) config('seo.ping.timeout', 5))->get($url);

                if ($response->successful()) {
                    $delivered = true;

                    continue;
                }

                Log::warning('A search engine rejected a sitemap ping.', [
                    'team_id' => (int) $team->getKey(),
                    'status' => $response->status(),
                ]);
            } catch (ConnectionException $exception) {
                Log::warning('A sitemap ping could not be delivered.', [
                    'team_id' => (int) $team->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $delivered;
    }
}
