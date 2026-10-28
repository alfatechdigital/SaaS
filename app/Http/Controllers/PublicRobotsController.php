<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Response;

/**
 * `robots.txt` for a tenant's public page (Fase 3, tugas 3.5.7).
 *
 * Path-based tenancy means a crawler only ever fetches `/robots.txt` at the host
 * root, so this file is advisory until tenant domains exist (wave 2): the tenant
 * that owns the host is the only one whose robots file is read automatically.
 * It still matters for two reasons: it publishes the sitemap URL, and it is the
 * file a domain will serve once ADR-04 lands.
 *
 * A tenant whose page is hidden still gets a readable `robots.txt` (with
 * `Disallow: /`) instead of a 404 — a missing robots file means "index
 * everything", which is the opposite of what moderation decided.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.7, 3.5.9
 */
class PublicRobotsController extends Controller
{
    /**
     * Serve the tenant's robots policy.
     */
    public function __invoke(Team $team): Response
    {
        $lines = $team->isPublicPageVisible()
            ? [
                'User-agent: *',
                'Allow: /',
                'Sitemap: '.route('public.sitemap', ['team' => $team->slug]),
            ]
            : [
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
