<?php

namespace App\Support\WhatsApp;

use App\Enums\WhatsAppMessageDirection;
use App\Models\Team;
use App\Models\WhatsAppMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Turns Meta's delivery callbacks into rows a tenant can inspect (tugas 11 Nov).
 *
 * Meta sends one webhook per transition — `sent`, `delivered`, `read`, `failed` —
 * each carrying only the `wamid` and a timestamp. Earlier versions ignored them,
 * so "did the customer actually receive it?" had no answer anywhere in the app.
 *
 * Three rules:
 *
 * 1. **Transitions only move forward.** A late `delivered` must not overwrite a
 *    `read`, and a retried `sent` must not erase a `failed` cause.
 * 2. **Unknown ids are ignored, not created.** A status for a message this tenant
 *    never sent belongs to someone else's number; inventing a row would put a
 *    stranger's message id in the tenant's inbox.
 * 3. **Failures keep the reason.** Meta's error text is the only thing that
 *    explains a silent non-delivery, so it is stored, not just logged.
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-3.4
 */
final class WhatsAppDeliveryLog
{
    /**
     * Order of progress; a status never moves a message backwards.
     *
     * @var array<string, int>
     */
    private const STAGES = [
        'logged' => 0,
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
        'failed' => 4,
    ];

    /**
     * Apply every status callback in the payload.
     *
     * @param  array<int, array<string, mixed>>  $statuses
     * @return int Number of message rows updated.
     */
    public function apply(Team $team, array $statuses): int
    {
        $updated = 0;

        foreach ($statuses as $status) {
            if ($this->applyOne($team, $status)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyOne(Team $team, array $status): bool
    {
        $wamid = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (! is_string($wamid) || ! is_string($state) || ! array_key_exists($state, self::STAGES)) {
            Log::warning('Ignored a WhatsApp delivery status without a usable id or state.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return false;
        }

        $message = WhatsAppMessage::query()
            ->where('wamid', $wamid)
            ->where('direction', WhatsAppMessageDirection::Outbound)
            ->first();

        if ($message === null) {
            Log::info('Ignored a WhatsApp delivery status for a message this tenant did not send.', [
                'team_id' => (int) $team->getKey(),
                'status' => $state,
            ]);

            return false;
        }

        if ($this->stage($message->status) > self::STAGES[$state]) {
            return false;
        }

        $at = $this->timestamp($status);

        $message->status = $state;

        match ($state) {
            'delivered' => $message->delivered_at = $at,
            'read' => $message->read_at = $at,
            'failed' => $message->failed_at = $at,
            default => null,
        };

        if ($state === 'failed') {
            $message->failure_reason = $this->failureReason($status);
        }

        $message->save();

        return true;
    }

    private function stage(?string $status): int
    {
        return self::STAGES[$status ?? ''] ?? -1;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function timestamp(array $status): CarbonImmutable
    {
        $timestamp = $status['timestamp'] ?? null;

        if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
        }

        return CarbonImmutable::now();
    }

    /**
     * Meta nests the explanation: `errors[0].title` plus `error_data.details`.
     *
     * @param  array<string, mixed>  $status
     */
    private function failureReason(array $status): ?string
    {
        $error = $status['errors'][0] ?? null;

        if (! is_array($error)) {
            return null;
        }

        $title = $error['title'] ?? null;
        $details = $error['error_data']['details'] ?? null;
        $parts = array_filter([$title, $details], 'is_string');

        return $parts === [] ? null : mb_strimwidth(implode(' — ', $parts), 0, 500, '…');
    }
}
