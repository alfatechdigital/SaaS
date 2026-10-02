<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Database\Seeders\AlfatechDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the operator flag set up by the demo seeder (PDR-05).
 *
 * Without this, the only way to notice that nobody can reach the platform panel
 * (or that too many people can) would be to log in and look.
 */
class PlatformSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_marks_only_the_operator_as_platform_admin()
    {
        $this->seed(AlfatechDemoSeeder::class);

        $admins = User::all()
            ->filter(fn (User $user): bool => $user->isPlatformAdmin())
            ->pluck('email')
            ->values()
            ->all();

        $this->assertEquals(['digitalalfatech@gmail.com'], $admins);
    }
}
