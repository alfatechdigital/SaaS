<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proves that a WhatsApp webhook really came from Meta.
 *
 * The webhook has no session and no user, so the signature is the only thing that
 * separates a real notification from anyone who knows the URL. Meta signs the raw
 * request body with the app secret; the digest must be computed over
 * `$request->getContent()`, never over decoded-and-re-encoded JSON, because the
 * bytes are what was signed.
 *
 * A missing `app secret` is refused rather than skipped: accepting unsigned
 * payloads because the deployment forgot to configure one would turn a
 * configuration mistake into an open door.
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-3.2
 */
final class VerifyWhatsAppSignature
{
    private const HEADER = 'X-Hub-Signature-256';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('whatsapp.webhook.app_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Refusing a WhatsApp webhook because WHATSAPP_APP_SECRET is not configured.');

            abort(403);
        }

        $provided = (string) $request->header(self::HEADER, '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Rejected a WhatsApp webhook with an invalid signature.', [
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
