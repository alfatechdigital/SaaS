<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyMidtransSignature;
use App\Support\Billing\MidtransNotification;
use App\Support\Billing\MidtransWebhookProcessor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint Midtrans calls after a transaction changes (B-2, tugas 24 Nov).
 *
 * Registered outside the `web` middleware group — the caller is a provider, not a
 * browser — and the signature middleware has already rejected anything that is not
 * from Midtrans. The controller itself only exists to hand the verified
 * notification to the processor.
 *
 * A 200 is returned only when the processor reached a decision. A processing error
 * becomes a 5xx on purpose, because that is Midtrans' cue to retry.
 *
 * @see VerifyMidtransSignature
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-2.5
 */
final class MidtransWebhookController extends Controller
{
    public function receive(Request $request, MidtransWebhookProcessor $processor): Response
    {
        $notification = $request->attributes->get(VerifyMidtransSignature::ATTRIBUTE);

        if (! $notification instanceof MidtransNotification) {
            // Defensive: the middleware only lets a verified notification through.
            abort(400, 'Unverified notification');
        }

        $processor->process($notification);

        return response('OK', 200, ['Content-Type' => 'text/plain']);
    }
}
