<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ContentItemResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TransactionResource;
use App\Models\ActivityLog;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\Project;
use App\Models\TeamInvitation;
use App\Models\Transaction;
use App\Support\CurrentTeam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     *
     * Renders `admin/dashboard/Index` so the page uses `AdminLayout` (the Alfatech
     * shell) rather than the starter kit layout. See docs/IMPLEMENTATION_PLAN.md
     * TBD-07.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Activate the request's tenant. Every query below is confined by the
        // team global scope, so none of them needs an explicit `->forTeam()`.
        $team = CurrentTeam::from($request);

        // A module the plan does not include is empty here, not just closed on
        // its own page: the dashboard must not advertise data the tenant cannot
        // open (Fase 4, tugas 4.6.3). The prop keys stay, so the page keeps its
        // shape for every plan.
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $projects = $team->can(Feature::ProjectManagement)
            ? Project::query()
                ->with('pic:id,name,job_title')
                ->orderBy('deadline')
                ->get()
            : collect();

        $leads = $team->can(Feature::LeadManagement)
            ? Lead::query()
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $contents = $team->can(Feature::ContentPlanner)
            ? ContentItem::query()
                ->with('assignee:id,name')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
            : collect();

        $transactions = $team->can(Feature::Finance)
            ? Transaction::query()
                ->orderByDesc('date')
                ->get()
            : collect();

        $activityLogs = ActivityLog::query()
            ->with('performedBy:id,name')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('admin/dashboard/Index', [
            'projects' => ProjectResource::collection($projects),
            'leads' => LeadResource::collection($leads),
            'contents' => ContentItemResource::collection($contents),
            'transactions' => TransactionResource::collection($transactions),
            'activityLogs' => ActivityLogResource::collection($activityLogs),
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
