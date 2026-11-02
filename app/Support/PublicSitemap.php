<?php

namespace App\Support;

use App\Jobs\PingSitemapJob;
use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The single builder for a tenant's `sitemap.xml` (Fase 3, tugas 3.5.8; B-8).
 *
 * Two callers need the same document for different reasons: the HTTP controller
 * that serves it to a crawler, and {@see PingSitemapJob}, which pings
 * search engines only when the document actually changed. Building it in one
 * place is what makes those two agree — a divergence would mean pinging about a
 * sitemap the crawler will never see.
 *
 * The caller must have the tenant active ({@see CurrentTeam::activate()} or
 * {@see TenantContext::runFor()}), because the queries below are team-scoped and
 * fail loudly without one.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.8
 */
final class PublicSitemap
{
    public function __construct(private readonly Team $team) {}

    public static function for(Team $team): self
    {
        return new self($team);
    }

    /**
     * The entries of the sitemap.
     *
     * The public page is a single document — portfolio items are sections of it,
     * not separate URLs — so there is exactly one entry. What the portfolio
     * contributes is `lastmod`: publishing an item changes the page crawlers
     * should re-read. Only published items count, so drafts never leak a change
     * signal to the outside world.
     *
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function urls(): array
    {
        $profile = CompanyProfile::query()->first();

        $latestPortfolioUpdate = PortfolioItem::query()
            ->where('published', true)
            ->orderByDesc('updated_at')
            ->value('updated_at');

        return [[
            'loc' => PublicPageSeo::for($this->team, $profile, collect())->canonical(),
            'lastmod' => $this->newest($profile?->updated_at, $latestPortfolioUpdate),
        ]];
    }

    /**
     * The rendered XML document.
     *
     * Rendered through Blade so XML escaping (`&` in a slug or address) stays in
     * one place instead of being re-derived with `sprintf`.
     */
    public function xml(): string
    {
        return view('seo.sitemap', ['urls' => $this->urls()])->render();
    }

    /**
     * A stable digest of the rendered document.
     *
     * `PingSitemapJob` stores this per tenant and skips engines when the digest is
     * unchanged, so re-saving the same profile does not turn into a stream of
     * pings. It is a hash of the output, not of the input rows: a change that does
     * not alter what crawlers read (a draft edit, a column no entry uses) is
     * correctly ignored.
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->xml());
    }

    /**
     * The most recent change among the sources that feed the page, as `Y-m-d`.
     *
     * Sitemaps accept a full timestamp, but a date is enough for re-crawl
     * scheduling and avoids leaking how often a tenant edits its profile.
     */
    private function newest(?CarbonInterface $profileUpdatedAt, mixed $portfolioUpdatedAt): ?string
    {
        $newest = collect([
            $profileUpdatedAt,
            $portfolioUpdatedAt === null ? null : Carbon::parse((string) $portfolioUpdatedAt),
        ])->filter()->max();

        return $newest?->toDateString();
    }
}
