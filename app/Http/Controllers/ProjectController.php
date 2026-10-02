<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Requests\Project\SaveProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\CurrentTeam;
use App\Support\TeamMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display the project board.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageProjects);

        $projects = Project::query()
            ->forTeam($team)
            ->with('pic:id,name,job_title')
            ->orderBy('deadline')
            ->get();

        $tasks = Task::query()
            ->forTeam($team)
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get();

        return Inertia::render('admin/projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'tasks' => TaskResource::collection($tasks),
            'members' => TeamMembers::options($team),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(SaveProjectRequest $request): RedirectResponse
    {
        Project::create([
            'team_id' => $request->team()->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Proyek ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified project.
     */
    public function update(SaveProjectRequest $request): RedirectResponse
    {
        $model = Project::query()
            ->forTeam($request->team())
            ->findOrFail((int) $request->route('project'));

        $model->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Proyek diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageProjects);

        Project::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('project'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Proyek dihapus.'),
        ]);

        return back();
    }
}
