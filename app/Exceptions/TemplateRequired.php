<?php

namespace App\Exceptions;

use App\Support\WhatsApp\WhatsAppSender;
use RuntimeException;

/**
 * Free text was about to be sent outside Meta's 24-hour service window.
 *
 * Retrying cannot fix this: the customer has not written for more than a day, so
 * only an approved template will be delivered. It is therefore a permanent
 * failure, and the message names the template route so the fix is obvious from the
 * log.
 *
 * @see WhatsAppSender
 */
final class TemplateRequired extends RuntimeException
{
    public static function for(string $to, int $windowHours): self
    {
        return new self(sprintf(
            'WhatsApp free text to [%s] is not allowed more than %d hours after the '
            .'customer last wrote; send an approved template instead.',
            $to,
            $windowHours,
        ));
    }
}
