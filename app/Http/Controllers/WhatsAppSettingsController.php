<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\Feature;
use App\Enums\TeamPermission;
use App\Http\Requests\WhatsApp\SaveWhatsAppSettingsRequest;
use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a tenant connect its own WhatsApp Business number (B-3, follow-up 12 Nov).
 *
 * Storing the credentials is configuration, not sending: the page is reachable
 * even when the plan does not include `whatsapp_bot`, and it says so. A tenant
 * that buys the bot later should not have to discover a hidden form first, and
 * the sender already refuses to send without the entitlement.
 *
 * The access token is never sent back to the browser. `has_token` tells the form
 * whether to ask for a new one, and a blank field on save keeps the stored value.
 */
class WhatsAppSettingsController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Show the connection status and the credential form.
     */
    public function edit(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::UpdateTeam);

        return Inertia::render('admin/settings/WhatsApp', [
            'whatsapp' => $this->settings($team),
        ]);
    }

    /**
     * Store the tenant's own number credentials.
     */
    public function update(SaveWhatsAppSettingsRequest $request): RedirectResponse
    {
        $team = $request->team();
        $data = $request->validated();
        $token = $data['access_token'] ?? null;

        if ($token === null || $token === '') {
            $token = $team->whatsapp_access_token;
        }

        if (! is_string($token) || $token === '') {
            // Unreachable through the form (`requiredIf`), but the model contract
            // must not depend on the UI for its own invariant.
            throw ValidationException::withMessages([
                'access_token' => __('Access token wajib diisi untuk menghubungkan nomor.'),
            ]);
        }

        $team->connectWhatsApp(
            $data['phone_number_id'],
            $token,
            $data['business_account_id'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Nomor WhatsApp berhasil dihubungkan.'),
        ]);

        return back();
    }

    /**
     * Forget the tenant's WhatsApp credentials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::UpdateTeam);

        $team->disconnectWhatsApp();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Nomor WhatsApp diputuskan.'),
        ]);

        return back();
    }

    /**
     * The connection state the page may see. Never includes the token itself.
     *
     * @return array<string, mixed>
     */
    private function settings(Team $team): array
    {
        return [
            'connected' => $team->isWhatsAppConnected(),
            'has_token' => $team->isWhatsAppConnected(),
            'phone_number_id' => $team->whatsapp_phone_number_id,
            'business_account_id' => $team->whatsapp_business_account_id,
            'connected_at' => $team->whatsapp_connected_at?->toISOString(),
            'driver' => (string) config('whatsapp.driver'),
            'entitled' => $team->can(Feature::WhatsAppBot),
            'webhook_url' => url('/webhooks/whatsapp'),
        ];
    }
}
