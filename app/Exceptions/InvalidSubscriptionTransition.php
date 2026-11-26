<?php

namespace App\Exceptions;

use App\Enums\SubscriptionStatus;
use RuntimeException;

/**
 * Thrown when a subscription is asked to move somewhere the state machine forbids.
 *
 * The rules live in {@see SubscriptionStatus}; the service fails loudly rather
 * than writing an impossible status, because a wrong subscription status is a
 * billing error, not a cosmetic one: it decides whether the tenant keeps access.
 */
final class InvalidSubscriptionTransition extends RuntimeException
{
    public static function between(SubscriptionStatus $from, SubscriptionStatus $to): self
    {
        return new self(sprintf(
            'Subscription cannot move from [%s] to [%s].',
            $from->value,
            $to->value,
        ));
    }
}
