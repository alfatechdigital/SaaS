<?php

namespace App\Jobs;

use App\Exceptions\WhatsAppDeliveryFailed;
use App\Models\Team;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\WhatsAppBot;
use Illuminate\Support\Facades\Log;

/**
 * Answer one inbound WhatsApp message (B-3, tugas 10 Nov).
 *
 * Queued rather than answered inside the webhook: Meta expects a response in
 * seconds and retries otherwise, while the reply itself is an outbound HTTP call
 * that can be slow or fail. The webhook stores the message and returns; this job
 * does the talking.
 *
 * `processed_at` makes it safe to retry — a job that already answered returns
 * immediately instead of greeting the same customer twice.
 */
final class ReplyToWhatsAppMessageJob extends TenantJob
{
    public int $tries = 3;

    public function __construct(int $teamId, public int $messageId)
    {
        parent::__construct($teamId);

        $this->onQueue((string) config('whatsapp.queue', 'default'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    protected function handleForTenant(Team $team): void
    {
        $message = WhatsAppMessage::query()->find($this->messageId);

        if ($message === null) {
            Log::warning('Skipping a WhatsApp reply because the message no longer exists.', [
                'team_id' => (int) $team->getKey(),
                'message_id' => $this->messageId,
            ]);

            return;
        }

        if ($message->processed_at !== null) {
            return;
        }

        try {
            app(WhatsAppBot::class)->handle($team, $message);
        } catch (WhatsAppDeliveryFailed $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $this->fail($exception);

            return;
        }

        // Marked after the bot ran, so a failed delivery is retried rather than
        // silently counted as answered.
        $message->processed_at = now();
        $message->save();
    }
}
