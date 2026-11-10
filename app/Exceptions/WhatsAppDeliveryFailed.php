<?php

namespace App\Exceptions;

use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * The provider did not accept the message.
 *
 * The retryable flag is the whole point of this class: a 500 or a timeout is worth
 * another attempt, while a 401 (bad token) or 400 (bad number) will fail exactly
 * the same way forever. Retrying the second kind is how a queue fills with work
 * that can never succeed.
 *
 * The response body is truncated and included because Meta explains *why* it
 * refused (invalid recipient, template required, rate limit); without it the log
 * line says nothing actionable.
 *
 * @see SendWhatsAppMessageJob
 */
final class WhatsAppDeliveryFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function refused(Response $response): self
    {
        $status = $response->status();

        return new self(
            sprintf(
                'WhatsApp delivery was refused with HTTP %d: %s',
                $status,
                mb_strimwidth($response->body(), 0, 500, '…'),
            ),
            retryable: $status === 429 || $status >= 500,
            status: $status,
        );
    }

    public static function unreachable(ConnectionException $exception): self
    {
        return new self(
            'WhatsApp delivery could not reach the provider: '.$exception->getMessage(),
            retryable: true,
            previous: $exception,
        );
    }
}
