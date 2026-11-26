<?php

namespace App\Http\Middleware;

use App\Support\Billing\MidtransNotification;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proves that a Midtrans webhook really came from Midtrans (B-2, tugas 24 Nov).
 *
 * Midtrans signs the notification itself: `signature_key` is SHA-512 over
 * `order_id + status_code + gross_amount + server_key`, using the exact strings it
 * sent. That is why the body is decoded once and kept as-is — never re-encoded.
 *
 * Three answers, deliberately different:
 * - **400** for a payload that is not a complete notification: nothing to verify.
 * - **503** when the server key is missing: temporary configuration problem, and a
 *   provider retry after the fix should succeed instead of being lost.
 * - **403** when the signature does not match: the caller is not Midtrans.
 *
 * The verified notification is put on the request, so the controller and its
 * processor never re-parse or re-verify anything.
 *
 * @see MidtransNotification
 */
final class VerifyMidtransSignature
{
    public const ATTRIBUTE = 'midtrans_notification';

    public function handle(Request $request, Closure $next): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            abort(400, 'Invalid payload');
        }

        $notification = MidtransNotification::fromArray($payload);

        if ($notification === null) {
            abort(400, 'Incomplete notification');
        }

        $serverKey = config('billing.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            Log::error('Refusing a Midtrans webhook because MIDTRANS_SERVER_KEY is not configured.');

            abort(503);
        }

        if (! $notification->hasValidSignature($serverKey)) {
            Log::warning('Rejected a Midtrans webhook with an invalid signature.', [
                'order_id' => $notification->orderId,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        $request->attributes->set(self::ATTRIBUTE, $notification);

        return $next($request);
    }
}
