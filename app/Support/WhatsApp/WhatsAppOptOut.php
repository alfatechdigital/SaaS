<?php

namespace App\Support\WhatsApp;

/**
 * Recognises an opt-out request in an inbound message.
 *
 * Deliberately a whole-message match after normalisation, not a `str_contains`:
 * "tolong jangan stop layanan saya" contains `stop` but is the opposite of an
 * opt-out, and stopping messages because of a sentence like that would lose a
 * customer. Matching the entire message (ignoring case, punctuation, emoji and
 * extra spaces) keeps false positives out.
 *
 * The keyword set is intentionally short and Indonesian-first, because a customer
 * who writes "BERHENTI" expects it to work immediately — not after being taught
 * the magic word.
 *
 * @see WhatsAppWebhookProcessor
 */
final class WhatsAppOptOut
{
    /**
     * Messages that mean "stop sending".
     *
     * @var array<int, string>
     */
    private const KEYWORDS = [
        'stop',
        'berhenti',
        'unsubscribe',
        'stop promo',
        'batal',
    ];

    /**
     * Whether the whole message is an opt-out request.
     */
    public static function matches(?string $body): bool
    {
        if ($body === null) {
            return false;
        }

        $normalised = self::normalise($body);

        return in_array($normalised, self::KEYWORDS, true);
    }

    /**
     * Lowercase, strip everything but letters/digits/spaces, collapse whitespace.
     */
    private static function normalise(string $body): string
    {
        $text = mb_strtolower(trim($body));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
