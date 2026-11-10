<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Data\WhatsAppCredentials;
use App\Exceptions\WhatsAppDeliveryFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API, called with the tenant's own number credentials.
 *
 * The endpoint is keyed by `phone_number_id` rather than by the business account:
 * one WABA can own several numbers, and a message must leave from the number the
 * tenant actually registered.
 *
 * Only text messages for now. Meta requires an approved template outside the
 * 24-hour customer-service window; that belongs to the 11 Nov task (templates,
 * log and quota), not here.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
final class MetaCloudDriver implements WhatsAppDriver
{
    public function send(WhatsAppCredentials $credentials, OutgoingWhatsAppMessage $message): ?string
    {
        try {
            $response = Http::withToken($credentials->accessToken)
                ->acceptJson()
                ->timeout((int) config('whatsapp.timeout', 10))
                ->post($this->endpoint($credentials), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $message->to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message->body,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw WhatsAppDeliveryFailed::unreachable($exception);
        }

        if ($response->failed()) {
            throw WhatsAppDeliveryFailed::refused($response);
        }

        $messageId = $response->json('messages.0.id');

        return is_string($messageId) ? $messageId : null;
    }

    private function endpoint(WhatsAppCredentials $credentials): string
    {
        return sprintf(
            '%s/%s/%s/messages',
            rtrim((string) config('whatsapp.graph.base_url'), '/'),
            (string) config('whatsapp.graph.version'),
            $credentials->phoneNumberId,
        );
    }
}
