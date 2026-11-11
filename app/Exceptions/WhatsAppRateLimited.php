<?php

namespace App\Exceptions;

use App\Jobs\SendWhatsAppMessageJob;
use App\Support\WhatsApp\WhatsAppSender;
use RuntimeException;

/**
 * The tenant is sending faster than its rate limit allows.
 *
 * Unlike a rejected token, this is worth retrying — but not immediately, or the
 * retry spends the same allowance. `retryAfter` tells the queued job how long to
 * wait, so a burst is smoothed instead of dropped.
 *
 * @see WhatsAppSender
 * @see SendWhatsAppMessageJob
 */
final class WhatsAppRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(sprintf(
            'WhatsApp sending for this tenant is rate limited; retry in %d seconds.',
            $retryAfter,
        ));
    }
}
