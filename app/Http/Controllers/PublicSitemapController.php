<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use App\Support\CurrentTeam;
use App\Support\PublicPageSeo;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * `sitemap.xml` for a tenant's public page (Fase 3, tugas 3.5.8).
 *
 * The public page is a single document — portfolio items are sections of it, not
 * separate URLs — so the sitemap has one entry. Listing portfolio items is
 * therefore not possible yet, and inventing `/p/{team}/portfolio/{id}` routes
 * would be a new product page (see KI3-4). What the portfolio *does* contribute
 * is `lastmod`: publishing an item changes the page crawlers should re-read.
 *
 * A hidden tenant (moderation or suspension) gets 404 rather than an empty
 * sitemap, so the URL is not advertised at all (tugas 3.5.9).
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.8, 3.5.9
 */
class PublicSitemapController extends Controller
{
    /**
     * Serve the tenant's sitemap.
     */
    public function __invoke(Team $team): Response
    {
        abort_unless($team->isPublicPageVisible(), 404);

        // The models below are tenant-scoped and fail loudly without a tenant, and
        // this route has no `{current_team}` segment to resolve one from.
        CurrentTeam::activate($team);

        $profile = CompanyProfile::query()->first();

        $latestPortfolioUpdate = PortfolioItem::query()
            ->where('published', true)
            ->orderByDesc('updated_at')
            ->value('updated_at');

        $urls = [[
            'loc' => PublicPageSeo::for($team, $profile, collect())->canonical(),
            'lastmod' => $this->newest($profile?->updated_at, $latestPortfolioUpdate),
        ]];

        return response(
            view('seo.sitemap', ['urls' => $urls])->render(),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
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
