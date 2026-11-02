<?php

namespace App\Http\Controllers;

use App\Jobs\PingSitemapJob;
use App\Models\Team;
use App\Support\CurrentTeam;
use App\Support\PublicSitemap;
use Illuminate\Http\Response;

/**
 * `sitemap.xml` for a tenant's public page (Fase 3, tugas 3.5.8).
 *
 * The document itself is built by {@see PublicSitemap}, which
 * {@see PingSitemapJob} reads too: what a crawler receives and the
 * digest that decides whether engines are pinged therefore cannot drift.
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

        // The models behind the sitemap are tenant-scoped and fail loudly without
        // a tenant, and this route has no `{current_team}` segment to resolve one
        // from.
        CurrentTeam::activate($team);

        return response(
            PublicSitemap::for($team)->xml(),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
