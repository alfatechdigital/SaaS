<?php

namespace App\Exceptions;

use App\Support\Entitlements;
use RuntimeException;

/**
 * Thrown when a tenant resolves to a plan that `config/plans.php` does not define.
 *
 * Failing loudly is deliberate. The plan name comes from configuration today and
 * from `subscriptions` once billing lands, so an unknown value means a typo in
 * config or a subscription pointing at a deleted plan. Falling back to the
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
