<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// P-5 — one database holds every tenant, so the daily dump is not optional.
// It only runs if the host has `php artisan schedule:run` on a cron; the README
// says so, because a scheduled command nobody runs is not a backup.
Schedule::command('db:backup')->dailyAt('02:00')->description('Backup database harian');
