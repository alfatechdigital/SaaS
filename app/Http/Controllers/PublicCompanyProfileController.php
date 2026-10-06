<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyProfileResource;
use App\Http\Resources\PortfolioItemResource;
use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use App\Support\CurrentTeam;
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
        // Moderation (PDR-12): a deactivated page must be gone, not merely
        // hidden. 404 rather than 403 so it does not confirm that the page
        // exists at all.
        abort_unless($team->public_page_enabled, 404);

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

        return Inertia::render('public/company-profile', [
            'team' => [
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'profile' => $profile === null
                ? null
                : CompanyProfileResource::make($profile)->resolve(),
            'portfolio' => PortfolioItemResource::collection($portfolio),
        ]);
    }
}
