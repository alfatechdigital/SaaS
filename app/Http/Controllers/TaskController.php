<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Requests\Task\SaveTaskRequest;
use App\Models\Task;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Store a newly created task.
     */
    public function store(SaveTaskRequest $request): RedirectResponse
    {
        Task::create([
            'team_id' => $request->team()->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tugas ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified task.
     */
    public function update(SaveTaskRequest $request): RedirectResponse
    {
        $model = Task::query()
            ->findOrFail((int) $request->route('task'));

        $model->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tugas diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageProjects);

        Task::query()
            ->findOrFail((int) $request->route('task'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tugas dihapus.'),
        ]);

        return back();
    }
}
