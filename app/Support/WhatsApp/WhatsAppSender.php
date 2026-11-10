<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Enums\Feature;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * The only supported way to send a WhatsApp message for a tenant.
 *
 * Two refusals happen before the provider is called, and both are logged rather
 * than thrown: a background job that reaches this point with a Basic plan or a
 * disconnected number is a stale job, not a bug worth retrying three times.
 *
 * 1. **Entitlement** — `whatsapp_bot` is a Plus-and-above feature (§B.4.1).
 * 2. **Connection** — the tenant must have stored its own number credentials,
 *    because there is no platform-wide sender to fall back to.
 *
 * `null` therefore means "nothing was sent", and the log line says why. A real
 * provider failure is not swallowed: {@see WhatsAppDeliveryFailed} carries whether
 * another attempt is worth making.
 */
final class WhatsAppSender
{
    public function __construct(private readonly WhatsAppDriver $driver) {}

    /**
     * Send one message for the tenant, or return `null` when it must not be sent.
     *
     * @throws WhatsAppDeliveryFailed when the provider refuses or is unreachable
     */
    public function sendFor(Team $team, OutgoingWhatsAppMessage $message): ?string
    {
        if (! $team->can(Feature::WhatsAppBot)) {
            Log::info('Skipping a WhatsApp message because the tenant plan does not include the bot.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return null;
        }

        $credentials = $team->whatsappCredentials();

        if ($credentials === null) {
            Log::info('Skipping a WhatsApp message because the tenant has not connected a number.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return null;
        }

        $messageId = $this->driver->send($credentials, $message);

        Log::info('WhatsApp message accepted by the provider.', [
            'team_id' => (int) $team->getKey(),
            'phone_number_id' => $credentials->phoneNumberId,
            'provider_message_id' => $messageId,
        ]);

        return $messageId;
    }
}
