<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Requests\ContentItem\SaveContentItemRequest;
use App\Http\Resources\ContentItemResource;
use App\Models\ContentItem;
use App\Support\CurrentTeam;
use App\Support\TeamMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentItemController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display the social media content planner.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageContent);

        $contents = ContentItem::query()
            ->forTeam($team)
            ->with('assignee:id,name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/content/Index', [
            'contents' => ContentItemResource::collection($contents),
            'members' => TeamMembers::options($team),
        ]);
    }

    /**
     * Store a newly created content item.
     */
    public function store(SaveContentItemRequest $request): RedirectResponse
    {
        ContentItem::create([
            'team_id' => $request->team()->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Konten ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified content item.
     */
    public function update(SaveContentItemRequest $request): RedirectResponse
    {
        $model = ContentItem::query()
            ->forTeam($request->team())
            ->findOrFail((int) $request->route('content'));

        $model->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Konten diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified content item.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageContent);

        ContentItem::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('content'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Konten dihapus.'),
        ]);

        return back();
    }
}
