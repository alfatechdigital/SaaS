<?php

namespace App\Exceptions;

use App\Support\Entitlements;
use RuntimeException;

/**
 * Thrown when a tenant resolves to a plan that `config/plans.php` does not define.
 *
 * Failing loudly is deliberate. The plan name comes from a tenant's running
 * subscription, so an unknown value means a subscription pointing at a plan that
 * config no longer defines. Falling back to the
 * default plan would silently hand a paying tenant a different feature set — the
 * kind of bug that only surfaces as a support ticket.
 *
 * @see Entitlements::planCode()
 * @see \docs\implementation\phase-04-plan-dan-entitlement.md tugas 4.3.1
 */
final class UnknownPlan extends RuntimeException
{
    public static function named(string $planCode): self
    {
        return new self(sprintf(
            'Plan [%s] is not defined in config/plans.php.',
            $planCode,
        ));
    }
}
