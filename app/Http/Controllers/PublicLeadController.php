<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Http\Requests\PublicLeadRequest;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Receives the public consultation form and files it as a new lead.
 *
 * This is the only unauthenticated write endpoint in the application, so the
 * route is rate limited and the request is validated by `PublicLeadRequest`.
 */
class PublicLeadController extends Controller
{
    /**
     * Store a consultation request as a new lead.
     */
    public function store(PublicLeadRequest $request, Team $team): RedirectResponse
    {
        // A deactivated public page must not accept submissions either (PDR-12).
        abort_unless($team->public_page_enabled, 404);

        $data = $request->validated();

        Lead::create([
            'team_id' => $team->id,
            'company_name' => $data['company_name'] ?: $data['contact_name'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?: null,
            'source' => 'Website Publik (Form Konsultasi)',
            'potential_project' => $data['service_type'],
            'estimated_value' => $data['budget_estimate'],
            'status' => LeadStatus::New,
            'next_follow_up' => 'Segera hubungi (inbound website)',
            'notes' => $data['notes'] ?: null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permintaan konsultasi terkirim. Tim kami akan segera menghubungi Anda.'),
        ]);

        return back();
    }
}
