<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Data\WhatsAppCredentials;
use Illuminate\Support\Facades\Log;

/**
 * Sends nothing; writes what would have been sent.
 *
 * The default driver, so a fresh clone, a test run or a staging environment can
 * exercise the whole path (entitlement, credentials, queue, retries) without
 * messaging a real person or spending a conversation credit.
 */
final class LogDriver implements WhatsAppDriver
{
    public function send(WhatsAppCredentials $credentials, OutgoingWhatsAppMessage $message): ?string
    {
        Log::info('WhatsApp message (log driver, not sent).', [
            'phone_number_id' => $credentials->phoneNumberId,
            'to' => $message->to,
            'body' => $message->body,
        ]);

        return null;
    }
}
