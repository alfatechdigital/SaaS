<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

// Tinjauan lintas tenant (Fase 2, tugas 2.6.4): kedua tugas di bawah berjalan
// untuk SELURUH tenant sekaligus, dan keduanya memang boleh — tidak ada tugas
// yang butuh konteks tenant di sini.
//
// `team_invitations` sengaja TIDAK memakai BelongsToTeam (undangan bisa dibaca
// sebelum user punya tenant), jadi satu DELETE global sudah benar: mengiterasi
// tenant di sini hanya akan menambah query N+1 tanpa mengubah hasilnya.
//
// Kalau nanti ada tugas ber-scope tenant (mis. agregasi per tenant), jangan
// menyalin pola ini — bungkus tiap tenant dengan
// `TenantContext::runFor($team, ...)` atau dispatch `App\Jobs\TenantJob`.
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
