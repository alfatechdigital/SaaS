<?php

namespace App\Data;

use InvalidArgumentException;

/**
 * One outgoing WhatsApp message: free text or an approved template.
 *
 * Normalisation happens here so every driver sees the same shape Meta expects:
 * digits only, no `+`, no spaces, and an Indonesian `08…` number rewritten to
 * `628…`. Getting that wrong is invisible until a customer reports a message that
 * never arrived, so it is checked — not silently passed through.
 *
 * Two kinds exist because Meta treats them differently. Free text is only allowed
 * within 24 hours of the customer's last message; outside that window an approved
 * template is mandatory. The kind is explicit on the object so the sender can
 * enforce the rule instead of a driver guessing.
 */
final readonly class OutgoingWhatsAppMessage
{
    /**
     * @param  array<int, string>  $parameters  Template body parameters, in order.
     */
    private function __construct(
        public string $to,
        public ?string $body = null,
        public ?string $templateName = null,
        public ?string $templateLanguage = null,
        public array $parameters = [],
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

        return new self(self::normaliseNumber($to), body: $body);
    }

    /**
     * Build a template message, required outside the 24-hour service window.
     *
     * @param  array<int, string>  $parameters
     *
     * @throws InvalidArgumentException when the number or template name is unusable
     */
    public static function template(string $to, string $templateName, array $parameters = [], ?string $language = null): self
    {
        if (trim($templateName) === '') {
            throw new InvalidArgumentException('A WhatsApp template message needs a template name.');
        }

        return new self(
            to: self::normaliseNumber($to),
            templateName: $templateName,
            templateLanguage: $language ?? (string) config('whatsapp.template.language', 'id'),
            parameters: array_values(array_map('strval', $parameters)),
        );
    }

    /**
     * Whether this message must be sent as an approved template.
     */
    public function isTemplate(): bool
    {
        return $this->templateName !== null;
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
