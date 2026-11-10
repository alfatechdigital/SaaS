<?php

namespace App\Support\WhatsApp;

use App\Enums\TenantContextOrigin;
use App\Jobs\ReplyToWhatsAppMessageJob;
use App\Models\Team;
use App\Models\WhatsAppMessage;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Turns a WhatsApp webhook payload into rows, one tenant at a time.
 *
 * Meta posts *one* webhook to the app, so the payload itself has to say which
 * tenant it belongs to. `metadata.phone_number_id` is that answer: it is the
 * number that received the message, and each tenant stored its own when it
 * connected (05 Nov). Nothing about the destination comes from user input, which
 * is why routing on it is safe.
 *
 * Three rules keep it honest:
 *
 * 1. **Unknown number, no rows.** A phone number that no tenant registered is
 *    logged and dropped — creating a tenant from inbound traffic would let anyone
 *    with a WhatsApp account open one.
 * 2. **Retries store nothing new.** Meta re-delivers a webhook until it gets a
 *    200, so `wamid` is checked (and unique in the database) before inserting.
 * 3. **Writes happen inside the tenant's context**, because
 *    {@see WhatsAppMessage} is tenant-scoped and would otherwise refuse to write.
 *
 * Status callbacks (`sent`/`delivered`/`read`) are acknowledged and logged but not
 * stored yet: the delivery log is the 11 Nov task.
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-3.2
 */
final class WhatsAppWebhookProcessor
{
    /**
     * Store every message in the payload.
     *
     * @param  array<string, mixed>  $payload  The decoded webhook body.
     * @return int Number of newly stored messages.
     */
    public function process(array $payload): int
    {
        $stored = 0;

        foreach ($this->objects($payload['entry'] ?? []) as $entry) {
            $businessAccountId = $entry['id'] ?? null;
            $businessAccountId = is_string($businessAccountId) ? $businessAccountId : null;

            foreach ($this->objects($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $stored += $this->processValue($value, $businessAccountId);
            }
        }

        return $stored;
    }

    /**
     * Handle one `changes[].value`: route it to a tenant and store its messages.
     *
     * @param  array<string, mixed>  $value
     */
    private function processValue(array $value, ?string $businessAccountId): int
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            Log::warning('Ignored a WhatsApp webhook without a phone_number_id.');

            return 0;
        }

        $team = Team::query()->where('whatsapp_phone_number_id', $phoneNumberId)->first();

        if ($team === null) {
            Log::warning('Ignored a WhatsApp webhook for a number no tenant has connected.', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return 0;
        }

        if (! $this->belongsToAccount($team, $businessAccountId)) {
            Log::warning('Ignored a WhatsApp webhook whose business account does not match the tenant.', [
                'team_id' => (int) $team->getKey(),
                'phone_number_id' => $phoneNumberId,
                'business_account_id' => $businessAccountId,
            ]);

            return 0;
        }

        $statuses = $this->objects($value['statuses'] ?? []);

        if ($statuses !== []) {
            Log::info('Received WhatsApp delivery statuses; delivery logging is the 11 Nov task.', [
                'team_id' => (int) $team->getKey(),
                'count' => count($statuses),
            ]);
        }

        $messages = $this->objects($value['messages'] ?? []);

        if ($messages === []) {
            return 0;
        }

        /** @var int $stored */
        $stored = app(TenantContext::class)->runFor(
            $team,
            fn (): int => $this->store($team, $value, $messages),
            TenantContextOrigin::Webhook,
        );

        return $stored;
    }

    /**
     * Insert the messages that are not already stored.
     *
     * @param  array<string, mixed>  $value
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function store(Team $team, array $value, array $messages): int
    {
        $displayPhoneNumber = $value['metadata']['display_phone_number'] ?? null;
        $toNumber = is_string($displayPhoneNumber) ? $this->digits($displayPhoneNumber) : null;
        $stored = 0;

        foreach ($messages as $message) {
            $wamid = $message['id'] ?? null;
            $from = $message['from'] ?? null;

            if (! is_string($wamid) || $wamid === '' || ! is_string($from) || $from === '') {
                Log::warning('Skipped a WhatsApp message without an id or sender.', [
                    'team_id' => (int) $team->getKey(),
                ]);

                continue;
            }

            if (WhatsAppMessage::query()->where('wamid', $wamid)->exists()) {
                continue;
            }

            // Created through the relation so the owning tenant is set without
            // making `team_id` mass-assignable.
            $created = $team->whatsappMessages()->create([
                'direction' => 'inbound',
                'wamid' => $wamid,
                'from_number' => $this->digits($from),
                'to_number' => $toNumber,
                'type' => is_string($message['type'] ?? null) ? $message['type'] : 'text',
                'body' => $this->body($message),
                'provider_payload' => $message,
                'received_at' => $this->receivedAt($message),
            ]);

            // The bot answers out of band: Meta wants this response in seconds,
            // and the reply is an outbound call that may fail and be retried.
            ReplyToWhatsAppMessageJob::dispatch((int) $team->getKey(), (int) $created->getKey());

            $stored++;
        }

        return $stored;
    }

    /**
     * The text of a message, or `null` for media and other types.
     *
     * @param  array<string, mixed>  $message
     */
    private function body(array $message): ?string
    {
        $body = $message['text']['body'] ?? null;

        return is_string($body) ? $body : null;
    }

    /**
     * When the provider says the message was sent, falling back to now.
     *
     * @param  array<string, mixed>  $message
     */
    private function receivedAt(array $message): CarbonImmutable
    {
        $timestamp = $message['timestamp'] ?? null;

        if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
        }

        return CarbonImmutable::now();
    }

    /**
     * Whether the webhook's business account matches the tenant's, when both exist.
     */
    private function belongsToAccount(Team $team, ?string $businessAccountId): bool
    {
        if ($businessAccountId === null || $team->whatsapp_business_account_id === null) {
            return true;
        }

        return $team->whatsapp_business_account_id === $businessAccountId;
    }

    private function digits(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    /**
     * Keep only the array elements of a payload list, so a malformed entry cannot
     * reach the code that expects objects.
     *
     * @return array<int, array<string, mixed>>
     */
    private function objects(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
