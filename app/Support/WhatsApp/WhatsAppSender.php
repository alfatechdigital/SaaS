<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Enums\Feature;
use App\Exceptions\TemplateRequired;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Exceptions\WhatsAppRateLimited;
use App\Models\Team;
use App\Models\WhatsAppConversation;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The only supported way to send a WhatsApp message for a tenant.
 *
 * Six guards run before the provider is called, in an order that reflects what
 * each failure means:
 *
 * 1. **Entitlement** — `whatsapp_bot` is Plus-and-above (§B.4.1).
 * 2. **Connection** — the tenant must have stored its own number credentials.
 * 3. **Opt-out** — a contact who asked to stop is never messaged again, even if
 *    the tenant tries; the customer's request outranks the tenant's intent.
 * 4. **Rate limit** — per minute, throws so a queued job can wait instead of
 *    losing the message.
 * 5. **Monthly quota** — from the plan, logged and dropped: retrying cannot create
 *    allowance.
 * 6. **Service window** — free text more than 24 h after the customer last wrote
 *    is refused, and the caller must send an approved template instead.
 *
 * `null` means "nothing was sent, deliberately" and every such refusal is logged
 * with its reason. Real provider failures are not swallowed:
 * {@see WhatsAppDeliveryFailed} carries whether another attempt is worthwhile.
 */
final class WhatsAppSender
{
    public function __construct(
        private readonly WhatsAppDriver $driver,
        private readonly WhatsAppQuota $quota,
    ) {}

    /**
     * Send one message for the tenant, or return `null` when it must not be sent.
     *
     * @throws WhatsAppDeliveryFailed when the provider refuses or is unreachable
     * @throws WhatsAppRateLimited when the tenant is sending too fast to try now
     * @throws TemplateRequired when free text is no longer allowed for this contact
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

        $conversation = $this->conversationWith($team, $message->to);

        if ($conversation?->opted_out_at !== null) {
            Log::info('Skipping a WhatsApp message because the contact opted out.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return null;
        }

        if (! $this->hasCapacity($team)) {
            return null;
        }

        $this->enforceServiceWindow($conversation, $message);

        $messageId = $this->driver->send($credentials, $message);

        $this->quota->record($team);

        Log::info('WhatsApp message accepted by the provider.', [
            'team_id' => (int) $team->getKey(),
            'phone_number_id' => $credentials->phoneNumberId,
            'provider_message_id' => $messageId,
            'template' => $message->templateName,
        ]);

        return $messageId;
    }

    /**
     * Reserve a slot in the per-minute rate limit, then check the monthly quota.
     *
     * The rate limit is consulted first because it is the cheaper, self-healing
     * one: a caller that is merely too fast should wait, not be told its plan is
     * exhausted.
     */
    private function hasCapacity(Team $team): bool
    {
        $perMinute = max(1, (int) config('whatsapp.rate_limit.per_minute', 30));
        $key = 'whatsapp:send:'.(int) $team->getKey();

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw new WhatsAppRateLimited(RateLimiter::availableIn($key));
        }

        if (! $this->quota->hasRemaining($team)) {
            Log::warning('Skipping a WhatsApp message because the tenant monthly quota is exhausted.', [
                'team_id' => (int) $team->getKey(),
                'used' => $this->quota->used($team),
                'limit' => $this->quota->limit($team),
            ]);

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    /**
     * Refuse free text outside Meta's 24-hour service window.
     *
     * A contact this tenant has never heard from is outside the window by
     * definition: Meta only opens it when the *customer* writes first.
     *
     * @throws TemplateRequired
     */
    private function enforceServiceWindow(?WhatsAppConversation $conversation, OutgoingWhatsAppMessage $message): void
    {
        if ($message->isTemplate()) {
            return;
        }

        $hours = max(1, (int) config('whatsapp.service_window.hours', 24));
        $lastInbound = $conversation?->last_inbound_at;

        if ($lastInbound !== null && $lastInbound->greaterThan(now()->subHours($hours))) {
            return;
        }

        Log::warning('Refused WhatsApp free text outside the service window.', [
            'last_inbound_at' => $lastInbound?->toIso8601String(),
        ]);

        throw TemplateRequired::for($message->to, $hours);
    }

    private function conversationWith(Team $team, string $number): ?WhatsAppConversation
    {
        // Wrapped so the sender is safe for any caller, not only ones that already
        // run inside a tenant context (the queue job does, a console command
        // might not).
        return app(TenantContext::class)->runFor(
            $team,
            fn (): ?WhatsAppConversation => $team->whatsappConversations()
                ->where('contact_number', $number)
                ->first(),
        );
    }
}
