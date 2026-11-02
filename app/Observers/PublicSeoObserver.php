<?php

namespace App\Observers;

use App\Jobs\PingSitemapJob;
use App\Models\PortfolioItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

/**
 * Notices that something a crawler can read has changed (B-8, SEO automation).
 *
 * The observer answers one question per model — "would this change the public
 * page or its `sitemap.xml`?" — and hands the tenant to {@see PingSitemapJob}.
 * It deliberately does not decide *whether* a ping is needed: that requires the
 * rendered sitemap and its stored digest, which the job owns. Keeping the split
 * here means the observer stays cheap and runs on every save.
 *
 * Draft-only edits on a portfolio item are the one exception, filtered before
 * dispatch: a tenant with fifty drafts would otherwise queue a job per keystroke
 * that can never change anything public.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md
 */
final class PublicSeoObserver
{
    /**
     * React to a model that was created or updated.
     */
    public function saved(Model $model): void
    {
        if ($model instanceof PortfolioItem && ! $this->portfolioIsPublic($model)) {
            return;
        }

        if ($model instanceof Team && ! $this->teamChangedVisibility($model)) {
            return;
        }

        $this->dispatch($model);
    }

    /**
     * React to a model that was deleted.
     *
     * A deleted row is gone either way, so the only question is whether the
     * public page changes — and for every model observed here, it does.
     */
    public function deleted(Model $model): void
    {
        $this->dispatch($model);
    }

    /**
     * Whether an unpublished item was, or has become, part of the public page.
     *
     * `wasChanged()` matters for the transition itself: taking an item down has to
     * trigger a ping even though the row is no longer published, otherwise the
     * `lastmod` of the sitemap would stay at the item's last public edit.
     */
    private function portfolioIsPublic(PortfolioItem $item): bool
    {
        return (bool) $item->published || $item->wasChanged('published');
    }

    /**
     * Whether this save changed who may see the page, or where it lives.
     *
     * A tenant's name feeds `og:site_name` and its JSON-LD; `Team::updating()`
     * regenerates the slug from the name, so a rename also moves the canonical
     * URL and every sitemap `loc`.
     */
    private function teamChangedVisibility(Team $team): bool
    {
        return $team->wasRecentlyCreated
            || $team->wasChanged(['name', 'slug', 'public_page_enabled', 'suspended_at']);
    }

    /**
     * Queue a sitemap ping for the tenant behind the changed model.
     */
    private function dispatch(Model $model): void
    {
        $teamId = $model instanceof Team ? $model->getKey() : $model->getAttribute('team_id');

        if (! is_int($teamId) && ! is_string($teamId)) {
            return;
        }

        PingSitemapJob::dispatch((int) $teamId);
    }
}
