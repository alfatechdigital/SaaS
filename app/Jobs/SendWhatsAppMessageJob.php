<?php

namespace App\Jobs;

use App\Data\WhatsAppMessage;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Models\Team;
use App\Support\WhatsApp\WhatsAppSender;
use InvalidArgumentException;

/**
 * Deliver one WhatsApp message for one tenant (B-3, gelombang 1).
 *
 * Extends {@see TenantJob}, so the tenant is re-established from the payload
 * inside the worker — the entitlement check, the credentials lookup and the log
 * context all need it, and a worker runs jobs for many tenants in a row.
 *
 * Retry policy lives in two places on purpose:
 *
 * - **Here** for how many attempts and how far apart (`$tries`, `backoff()`).
 * - **In {@see WhatsAppDeliveryFailed}** for whether the failure is worth
 *   retrying at all. Retrying a rejected token or an invalid number just delays
 *   the same failure and burns queue time, so those fail immediately.
 */
final class SendWhatsAppMessageJob extends TenantJob
{
    /**
     * Three attempts is enough to ride out a provider blip without hammering an
     * endpoint that is genuinely down.
     */
    public int $tries = 3;

    /**
     * @param  string  $to  Recipient phone number, normalised by {@see WhatsAppMessage::text()}.
     * @param  string  $body  Message text.
     */
    public function __construct(int $teamId, public string $to, public string $body)
    {
        parent::__construct($teamId);

        $this->onQueue((string) config('whatsapp.queue', 'default'));
    }

    /**
     * Seconds to wait before attempt 2 and attempt 3.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    protected function handleForTenant(Team $team): void
    {
        try {
            app(WhatsAppSender::class)->sendFor($team, WhatsAppMessage::text($this->to, $this->body));
        } catch (WhatsAppDeliveryFailed $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $this->fail($exception);
        } catch (InvalidArgumentException $exception) {
            // A recipient that can never be valid, e.g. a number with too few digits.
            $this->fail($exception);
        }
    }
}
