<?php

namespace App\Data;

use App\Support\WhatsApp\WhatsAppSender;

/**
 * The outcome of asking the sender to deliver one message.
 *
 * A `?string` from the sender used to mean two opposite things: "nothing was
 * sent, deliberately" and "sent, but the driver has no provider id" (the log
 * driver). Callers that record a message or advance a conversation must tell
 * them apart, so the sender returns this instead.
 *
 * @see WhatsAppSender::sendFor()
 */
final readonly class WhatsAppSendResult
{
    private function __construct(
        public bool $sent,
        public ?string $messageId,
    ) {}

    /**
     * A guard refused the message; nothing left the application.
     */
    public static function skipped(): self
    {
        return new self(false, null);
    }

    /**
     * The provider accepted the message. `$messageId` is `null` for drivers that
     * only write to the log.
     */
    public static function sent(?string $messageId): self
    {
        return new self(true, $messageId);
    }
}
