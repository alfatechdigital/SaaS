<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyWhatsAppSignature;
use App\Support\WhatsApp\WhatsAppWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint Meta calls: a subscribe handshake (GET) and notifications (POST).
 *
 * Both routes sit outside the `web` group — no session, no CSRF token, no Inertia
 * props — because the caller is a provider, not a browser. Authenticity comes from
 * something better suited to a machine: the verify token for the handshake and the
 * request signature for everything after it (see {@see VerifyWhatsAppSignature}).
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-3.2
 */
final class WhatsAppWebhookController extends Controller
{
    /**
     * Answer Meta's subscription handshake.
     *
     * The challenge must be echoed back byte-for-byte with a plain-text body; Meta
     * compares it, and anything else (JSON, an Inertia page, a redirect) fails the
     * subscription with a message that does not explain why.
     */
    public function verify(Request $request): Response
    {
        $expected = config('whatsapp.webhook.verify_token');

        if (! is_string($expected) || $expected === '') {
            Log::error('Refusing a WhatsApp verification handshake because WHATSAPP_VERIFY_TOKEN is not configured.');

            abort(403);
        }

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || ! is_string($token) || ! hash_equals($expected, $token)) {
            Log::warning('Rejected a WhatsApp verification handshake with a wrong mode or token.', [
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        return response((string) $challenge, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Accept a batch of notifications.
     *
     * Always answers 200 once the payload is well-formed, even when nothing was
     * stored: Meta retries any other answer, and a payload for an unknown number
     * will never become storable no matter how often it is delivered.
     */
    public function receive(Request $request, WhatsAppWebhookProcessor $processor): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response('Invalid payload', 400);
        }

        $stored = $processor->process($payload);

        Log::info('Processed a WhatsApp webhook.', ['stored_messages' => $stored]);

        return response('OK', 200, ['Content-Type' => 'text/plain']);
    }
}
