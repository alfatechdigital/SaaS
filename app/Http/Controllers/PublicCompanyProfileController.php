<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyProfileResource;
use App\Http\Resources\PortfolioItemResource;
use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use App\Support\CurrentTeam;
use App\Support\PublicPageSeo;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated company profile page.
 *
 * Picked up from item 2.11 of the migration plan. Only published portfolio items
 * are exposed, and the page is scoped to a single team via its slug.
 */
class PublicCompanyProfileController extends Controller
{
    /**
     * Display the public company profile for the given team.
     */
    public function __invoke(Team $team): Response
    {
        // Moderation (PDR-12) and suspension (Fase 2, tugas 2.3.5) both hide the
        // page: it must be gone rather than merely hidden, so 404 instead of 403 —
        // that avoids confirming the page exists at all. `X-Robots-Tag` makes the
        // "not indexable" part explicit for crawlers that already know the URL
        // (tugas 3.5.9).
        abort_unless($team->isPublicPageVisible(), 404, '', ['X-Robots-Tag' => 'noindex']);

        // The page is reachable without a `{current_team}` segment, so this is
        // the only place that can tell the team global scope which tenant the
        // queries below belong to.
        CurrentTeam::activate($team);

        $profile = CompanyProfile::query()
            ->first();

        $portfolio = PortfolioItem::query()
            ->where('published', true)
            ->orderByDesc('completion_date')
            ->get();

        $seo = PublicPageSeo::for($team, $profile, $portfolio);

        return Inertia::render('public/company-profile', [
            'team' => [
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'profile' => $profile === null
                ? null
                : CompanyProfileResource::make($profile)->resolve(),
            'portfolio' => PortfolioItemResource::collection($portfolio),
            // SEO meta (title, description, canonical, Open Graph, Twitter card,
            // JSON-LD) derived from the tenant's own data, so a tenant never
            // inherits another tenant's marketing copy or URL (tugas 3.5.2–3.5.6).
            'meta' => $seo->toArray(),
            // Raw head markup Inertia merges in through `serverHead` — the JSON-LD
            // script, which a Vue template cannot express (see resources/js/app.ts).
            'seoHead' => $seo->head(),
        ]);
    }
}
