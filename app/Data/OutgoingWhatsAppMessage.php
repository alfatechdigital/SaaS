<?php

namespace App\Data;

use InvalidArgumentException;

/**
 * One outgoing WhatsApp text message.
 *
 * Normalisation happens here so every driver sees the same shape Meta expects:
 * digits only, no `+`, no spaces, and an Indonesian `08…` number rewritten to
 * `628…`. Getting that wrong is invisible until a customer reports a message that
 * never arrived, so it is checked — not silently passed through.
 *
 * Text only for now (§B.4.1 wave 1). Template messages are part of the 11 Nov
 * task, which is the point where Meta's 24-hour session window starts to matter.
 */
final readonly class OutgoingWhatsAppMessage
{
    public function __construct(
        public string $to,
        public string $body,
    ) {
        //
    }

    /**
     * Build a plain text message for the given recipient.
     *
     * @throws InvalidArgumentException when the number cannot be a WhatsApp recipient
     */
    public static function text(string $to, string $body): self
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('A WhatsApp message cannot have an empty body.');
        }

        return new self(self::normaliseNumber($to), $body);
    }

    /**
     * Reduce a phone number to the digits-only form Cloud API accepts.
     */
    private static function normaliseNumber(string $to): string
    {
        $digits = preg_replace('/\D+/', '', $to) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException(
                'A WhatsApp recipient must be an international number of 8 to 15 digits.',
            );
        }

        return $digits;
    }
}
