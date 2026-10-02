<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grants or revokes platform-admin rights on an existing installation.
 *
 * The flag is only ever set by `AlfatechDemoSeeder`, so an installation that is
 * already running ends up with no platform admin at all and the platform panel
 * becomes unreachable. See F-7-6 in docs/plan/fase-7-tata-kelola-platform.md.
 */
class PromotePlatformAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:promote
                            {email : Email akun yang akan dijadikan platform admin}
                            {--revoke : Cabut hak platform admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Beri (atau cabut) hak platform admin pada sebuah akun';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $wantsPlatformRights = ! $this->option('revoke');

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if ($user === null) {
            $this->components->error("Tidak ada pengguna dengan email {$email}.");

            return self::FAILURE;
        }

        if ($user->isPlatformAdmin() === $wantsPlatformRights) {
            $this->components->warn($wantsPlatformRights
                ? "{$user->email} sudah menjadi platform admin."
                : "{$user->email} memang bukan platform admin.");

            return self::SUCCESS;
        }

        $user->update(['is_platform_admin' => $wantsPlatformRights]);

        $this->components->info($wantsPlatformRights
            ? "{$user->email} sekarang menjadi platform admin."
            : "Hak platform admin {$user->email} dicabut.");

        return self::SUCCESS;
    }
}
