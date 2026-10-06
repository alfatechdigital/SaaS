<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Http\Requests\CompanyProfile\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Show the company profile editor.
     */
    public function edit(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageCompanyProfile);

        $profile = CompanyProfile::query()->first();

        return Inertia::render('admin/company-profile/Edit', [
            'profile' => $profile === null
                ? null
                : CompanyProfileResource::make($profile)->resolve(),
        ]);
    }

    /**
     * Update the company profile.
     */
    public function update(UpdateCompanyProfileRequest $request): RedirectResponse
    {
        $team = $request->team();

        $profile = CompanyProfile::query()
            ->firstOrNew(['team_id' => $team->id]);

        $profile->fill($request->validated());
        $profile->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Profil perusahaan diperbarui.'),
        ]);

        return back();
    }
}
