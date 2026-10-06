<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\CurrentTeam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display the team activity log.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ViewActivityLog);

        $logs = ActivityLog::query()
            ->with('performedBy:id,name')
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/activity-logs/Index', [
            'logs' => ActivityLogResource::collection($logs),
        ]);
    }
}
