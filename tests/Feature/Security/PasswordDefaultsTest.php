<?php

namespace Tests\Feature\Security;

use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Fase 1 / tugas 1.6.5 — production password policy is actually configured.
 *
 * The policy lives in `AppServiceProvider::configureDefaults()` and only applies
 * when `APP_ENV=production`. Two tests: production must require the strict
 * policy, and local/testing must stay permissive so the demo accounts and the
 * test suite keep working.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.6.5
 */
class PasswordDefaultsTest extends TestCase
{
    public function test_production_requires_a_strict_password_policy(): void
    {
        $this->app['env'] = 'production';

        $rules = Password::defaults();

        $this->assertNotNull($rules);

        $rules = $rules->toPasswordRulesString();

        $this->assertStringContainsString('minlength: 12', $rules);
        $this->assertStringContainsString('lower', $rules);
        $this->assertStringContainsString('upper', $rules);
        $this->assertStringContainsString('digit', $rules);
        $this->assertStringContainsString('special', $rules);
    }

    public function test_local_environment_keeps_the_framework_defaults(): void
    {
        $rules = Password::defaults()->toPasswordRulesString();

        $this->assertStringNotContainsString('minlength: 12', $rules);
        $this->assertStringNotContainsString('special', $rules);
    }
}
