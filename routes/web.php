<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\PortfolioItemController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicCompanyProfileController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TenantTeamController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Public, unauthenticated company profile. Rate limited because the consultation
// form writes a lead. See docs/IMPLEMENTATION_PLAN.md §6 Fase 4.3.
Route::get('p/{team}', PublicCompanyProfileController::class)->name('public.company-profile');
Route::post('p/{team}/consultation', [PublicLeadController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('public.consultation.store');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Account & system preferences overview in the Alfatech shell.
        Route::get('settings', SettingsController::class)->name('settings.index');

        // The tenant's own team management, inside the tenant shell (PDR-04, PDR-07).
        Route::get('team', [TenantTeamController::class, 'index'])->name('team.index');
        Route::patch('team', [TenantTeamController::class, 'update'])->name('team.update');
        Route::patch('team/members/{user}', [TenantTeamController::class, 'updateMember'])->name('team.members.update');
        Route::delete('team/members/{user}', [TenantTeamController::class, 'destroyMember'])->name('team.members.destroy');
        Route::post('team/invitations', [TenantTeamController::class, 'storeInvitation'])->name('team.invitations.store');
        Route::delete('team/invitations/{invitation}', [TenantTeamController::class, 'destroyInvitation'])->name('team.invitations.destroy');

        Route::get('company-profile', [CompanyProfileController::class, 'edit'])->name('company-profile.edit');
        Route::put('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');

        // CRUD is modal-based, so no create/show/edit pages are needed. See ADR-12.
        Route::resource('projects', ProjectController::class)->except(['create', 'show', 'edit']);
        Route::resource('tasks', TaskController::class)->except(['create', 'show', 'edit']);
        Route::resource('leads', LeadController::class)->except(['create', 'show', 'edit']);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::resource('contents', ContentItemController::class)->except(['create', 'show', 'edit']);
        Route::resource('transactions', TransactionController::class)->except(['create', 'show', 'edit']);

        Route::resource('portfolio', PortfolioItemController::class)
            ->except(['create', 'show', 'edit'])
            ->parameters(['portfolio' => 'portfolioItem']);

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

// Platform layer: cross-tenant administration, deliberately outside the tenant
// shell so it can never be confused with a tenant's own screens (PDR-03, PDR-04).
Route::prefix('platform')
    ->middleware(['auth', 'verified', EnsurePlatformAdmin::class])
    ->name('platform.')
    ->group(function () {
        Route::get('tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
        Route::delete('tenants/{team}', [PlatformTenantController::class, 'destroy'])->name('tenants.destroy');
        Route::patch('tenants/{team}/public-page', [PlatformTenantController::class, 'updatePublicPage'])->name('tenants.public-page');
    });

require __DIR__.'/settings.php';
