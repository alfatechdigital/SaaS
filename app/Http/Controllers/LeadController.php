<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\TeamPermission;
use App\Http\Requests\Lead\SaveLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\Project;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display the lead pipeline.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageLeads);

        $leads = Lead::query()
            ->forTeam($team)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/leads/Index', [
            'leads' => LeadResource::collection($leads),
        ]);
    }

    /**
     * Store a newly created lead.
     */
    public function store(SaveLeadRequest $request): RedirectResponse
    {
        Lead::create([
            'team_id' => $request->team()->id,
            ...$request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Lead ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified lead.
     */
    public function update(SaveLeadRequest $request): RedirectResponse
    {
        $model = Lead::query()
            ->forTeam($request->team())
            ->findOrFail((int) $request->route('lead'));

        $model->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Lead diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified lead.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageLeads);

        Lead::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('lead'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Lead dihapus.'),
        ]);

        return back();
    }

    /**
     * Convert a won lead into a project.
     *
     * Ported from `convertLeadToProject()` in the template's `firestoreService`.
     * Differences: the project's PIC is the acting user rather than a hardcoded
     * engineer, and activity logging is handled by `ActivityObserver`.
     *
     * `projects.lead_id` (unique) records the source lead, so a repeated
     * conversion is refused instead of silently creating a second project.
     */
    public function convert(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageLeads);

        $lead = Lead::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('lead'));

        abort_unless($lead->status === LeadStatus::Won, 422, __('Hanya lead dengan status Menang yang dapat dikonversi.'));

        $alreadyConverted = Project::query()
            ->forTeam($team)
            ->where('lead_id', $lead->id)
            ->exists();

        if ($alreadyConverted) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Lead ini sudah pernah dikonversi menjadi proyek.'),
            ]);

            return back();
        }

        $deadline = now()->addDays(45);

        Project::create([
            'team_id' => $team->id,
            'lead_id' => $lead->id,
            'name' => $lead->potential_project ?: __('Proyek :company', ['company' => $lead->company_name]),
            'client_name' => $lead->company_name,
            'description' => __('Proyek hasil konversi dari pipeline leads CRM (:contact - :phone). Catatan: :notes', [
                'contact' => $lead->contact_name ?? '-',
                'phone' => $lead->phone ?? '-',
                'notes' => $lead->notes ?: __('Tidak ada catatan'),
            ]),
            'status' => ProjectStatus::Deal,
            'progress' => 5,
            'start_date' => now()->toDateString(),
            'deadline' => $deadline->toDateString(),
            'project_value' => $lead->estimated_value,
            'pic_id' => $request->user()?->id,
            'technologies' => ['React', 'Node.js', 'PostgreSQL'],
            'notes' => __('Konversi dari Leads CRM pada :date', [
                'date' => now()->translatedFormat('d F Y'),
            ]),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deal dimenangkan! Proyek baru telah dibuat.'),
        ]);

        return to_route('projects.index', ['current_team' => $team->slug]);
    }
}
