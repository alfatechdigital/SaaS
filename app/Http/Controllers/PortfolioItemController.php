<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Requests\PortfolioItem\SavePortfolioItemRequest;
use App\Http\Resources\PortfolioItemResource;
use App\Models\PortfolioItem;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioItemController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display a listing of the portfolio items.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManagePortfolio);

        $items = PortfolioItem::query()
            ->forTeam($team)
            ->orderByDesc('completion_date')
            ->get();

        return Inertia::render('admin/portfolio/Index', [
            'items' => PortfolioItemResource::collection($items),
        ]);
    }

    /**
     * Store a newly created portfolio item.
     */
    public function store(SavePortfolioItemRequest $request): RedirectResponse
    {
        PortfolioItem::create([
            'team_id' => $request->team()->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Portfolio ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified portfolio item.
     */
    public function update(SavePortfolioItemRequest $request): RedirectResponse
    {
        $item = PortfolioItem::query()
            ->forTeam($request->team())
            ->findOrFail((int) $request->route('portfolioItem'));

        $item->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Portfolio diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified portfolio item.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManagePortfolio);

        PortfolioItem::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('portfolioItem'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Portfolio dihapus.'),
        ]);

        return back();
    }
}
