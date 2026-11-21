<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\LeadStatus;
use App\Http\Requests\PublicContactRequest;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Teams\ContactMessageReceived;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Receives the public contact form (F-7, tugas 16 Nov).
 *
 * The old consultation form asked software-house questions and always wrote a
 * lead, although `lead_management` is a Plus feature. The general contact form
 * must work on every plan, so the two outcomes are separated:
 *
 * - **Always:** email the tenant owner with the message. This is the only copy
 *   for Basic tenants, which have no lead module.
 * - **Only when the plan includes `lead_management`:** also file the message as
 *   a lead so a Plus/Pro tenant sees it in the CRM pipeline.
 *
 * This is the only unauthenticated write endpoint in the application, so the
 * route is rate limited and the request is validated by `PublicContactRequest`.
 */
class PublicContactController extends Controller
{
    /**
     * Deliver a contact message to the tenant owner.
     */
    public function store(PublicContactRequest $request, Team $team): RedirectResponse
    {
        // A deactivated or suspended public page must not accept submissions
        // either (PDR-12, tugas 2.3.5).
        abort_unless($team->isPublicPageVisible(), 404);

        // Public routes reach their tenant through a route binding, so activate
        // it here to keep the "one active tenant per request" invariant intact.
        CurrentTeam::activate($team);

        /** @var array{name: string, contact: string, message: string} $data */
        $data = $request->validated();

        $this->notifyOwner($team, $data);

        if ($team->can(Feature::LeadManagement)) {
            $this->fileLead($team, $data);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pesan Anda terkirim. Tim kami akan menghubungi Anda melalui kontak yang diberikan.'),
        ]);

        return back();
    }

    /**
     * Send the message to the owner, the one recipient every plan has.
     *
     * @param  array{name: string, contact: string, message: string}  $data
     */
    private function notifyOwner(Team $team, array $data): void
    {
        $owner = $team->owner();

        if (! $owner instanceof User) {
            // A tenant without an owner is a data problem, not a reason to lose
            // the visitor's message silently: keep it in the log for follow-up.
            Log::warning('A public contact message could not be emailed because the team has no owner.', [
                'team_id' => (int) $team->getKey(),
                'contact' => $data['contact'],
            ]);

            return;
        }

        $owner->notify(new ContactMessageReceived(
            $team,
            $data['name'],
            $data['contact'],
            $data['message'],
        ));
    }

    /**
     * File the message in the CRM, only where the plan can show it.
     *
     * The single `contact` field may be a phone number or an email address, so it
     * is stored in whichever column it belongs to instead of guessing.
     *
     * @param  array{name: string, contact: string, message: string}  $data
     */
    private function fileLead(Team $team, array $data): void
    {
        $isEmail = filter_var($data['contact'], FILTER_VALIDATE_EMAIL) !== false;

        Lead::create([
            'team_id' => $team->id,
            'company_name' => $data['name'],
            'contact_name' => $data['name'],
            'phone' => $isEmail ? null : $data['contact'],
            'email' => $isEmail ? $data['contact'] : null,
            'source' => 'Website Publik (Form Kontak)',
            'potential_project' => Str::limit($data['message'], 255, ''),
            'status' => LeadStatus::New,
            'next_follow_up' => 'Segera hubungi (inbound website)',
            'notes' => $data['message'],
        ]);
    }
}
