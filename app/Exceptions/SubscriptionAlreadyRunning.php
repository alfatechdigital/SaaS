<?php

namespace App\Exceptions;

use App\Models\Team;
use App\Support\Billing\SubscriptionService;
use RuntimeException;

/**
 * Thrown when a tenant that already has a running subscription asks for a trial.
 *
 * One running subscription per tenant is also enforced by the database (see the
 * `subscriptions` migration), but a constraint violation surfaces as an opaque
 * `QueryException`. The service checks first so the caller gets a message that
 * says what actually happened.
 *
 * @see SubscriptionService::startTrial()
 */
final class SubscriptionAlreadyRunning extends RuntimeException
{
    public static function for(Team $team): self
    {
        return new self(sprintf(
            'Tenant [%d] already has a running subscription.',
            (int) $team->getKey(),
        ));
    }
}
