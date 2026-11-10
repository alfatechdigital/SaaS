<?php

namespace App\Support\WhatsApp;

use App\Data\OutgoingWhatsAppMessage;
use App\Enums\Feature;
use App\Enums\LeadStatus;
use App\Enums\WhatsAppConversationState;
use App\Enums\WhatsAppMessageDirection;
use App\Models\Team;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The two-way part of the WhatsApp bot (B-3, tugas 10 Nov).
 *
 * A deliberately small, deterministic conversation: greet → name → company → need
 * → lead. No AI, so the same message always produces the same answer and a tenant
 * can predict what their customers will read.
 *
 * Three rules matter more than the wording:
 *
 * 1. **State lives in the database, not in memory.** A webhook carries no session,
 *    so the conversation row is the only thing that knows the name was already
 *    asked for (see {@see WhatsAppConversation}).
 * 2. **The reply is only recorded after the provider accepted it.** If sending
 *    fails, the conversation does not advance and the retry re-asks the same
 *    question instead of skipping a step the customer never saw.
 * 3. **The bot stops where a human belongs.** `completed` and `handed_over` are
 *    silent, and a suspended tenant gets no automated answers at all.
 *
 * The lead is created only when the plan includes `lead_management`. A tenant who
 * bought `whatsapp_bot` as an add-on without the CRM gets the conversation, not a
 * lead screen they cannot open.
 */
final class WhatsAppBot
{
    private const MAX_NAME_LENGTH = 120;

    private const MAX_COMPANY_LENGTH = 255;

    private const MAX_PROJECT_LENGTH = 255;

    public function __construct(private readonly WhatsAppSender $sender) {}

    /**
     * Advance the conversation for one inbound message and answer it when needed.
     */
    public function handle(Team $team, WhatsAppMessage $inbound): void
    {
        if ($inbound->direction !== WhatsAppMessageDirection::Inbound) {
            return;
        }

        if ($team->isSuspended()) {
            Log::info('Skipping a WhatsApp bot reply for a suspended tenant.', [
                'team_id' => (int) $team->getKey(),
            ]);

            return;
        }

        if (! $team->can(Feature::WhatsAppBot) || ! $team->isWhatsAppConnected()) {
            return;
        }

        $conversation = $team->whatsappConversations()->firstOrCreate(
            ['contact_number' => $inbound->from_number],
            ['state' => WhatsAppConversationState::New],
        );

        $conversation->last_inbound_at = now();

        if (! $conversation->state->answersAutomatically()) {
            $conversation->save();

            return;
        }

        $reply = $this->replyFor($team, $conversation, $inbound);

        if ($reply === null) {
            $conversation->save();

            return;
        }

        // Sent first, saved second: a failure must leave the conversation where it
        // was, so the retry asks the same question again.
        $messageId = $this->sender->sendFor(
            $team,
            OutgoingWhatsAppMessage::text($inbound->from_number, $reply),
        );

        $conversation->last_outbound_at = now();
        $conversation->save();

        $this->recordOutbound($team, $inbound, $reply, $messageId);
    }

    /**
     * The answer for this message, updating the conversation in memory.
     */
    private function replyFor(Team $team, WhatsAppConversation $conversation, WhatsAppMessage $inbound): ?string
    {
        if ($inbound->type !== 'text' || $inbound->body === null) {
            // Anything else (image, audio, document) is not something this bot can
            // read yet; say so instead of pretending the step was completed.
            return 'Maaf, saya hanya bisa membaca pesan teks. Boleh dikirim ulang dalam bentuk teks?';
        }

        $text = trim($inbound->body);

        if ($text === '') {
            return null;
        }

        return match ($conversation->state) {
            WhatsAppConversationState::New => $this->askName($team, $conversation),
            WhatsAppConversationState::AwaitingName => $this->captureName($conversation, $text),
            WhatsAppConversationState::AwaitingCompany => $this->captureCompany($conversation, $text),
            WhatsAppConversationState::AwaitingMessage => $this->complete($team, $conversation, $inbound, $text),
            WhatsAppConversationState::Completed,
            WhatsAppConversationState::HandedOver => null,
        };
    }

    private function askName(Team $team, WhatsAppConversation $conversation): string
    {
        $conversation->state = WhatsAppConversationState::AwaitingName;

        return sprintf(
            'Halo! Terima kasih sudah menghubungi %s. Saya asisten otomatis. Boleh tahu nama Anda?',
            $team->name,
        );
    }

    private function captureName(WhatsAppConversation $conversation, string $text): string
    {
        $conversation->contact_name = Str::limit($text, self::MAX_NAME_LENGTH, '');
        $conversation->state = WhatsAppConversationState::AwaitingCompany;

        return sprintf('Terima kasih, %s. Nama perusahaan Anda?', $conversation->contact_name);
    }

    private function captureCompany(WhatsAppConversation $conversation, string $text): string
    {
        $conversation->company_name = Str::limit($text, self::MAX_COMPANY_LENGTH, '');
        $conversation->state = WhatsAppConversationState::AwaitingMessage;

        return sprintf('Baik, %s. Apa yang bisa kami bantu?', $conversation->company_name);
    }

    private function complete(Team $team, WhatsAppConversation $conversation, WhatsAppMessage $inbound, string $text): string
    {
        if ($team->can(Feature::LeadManagement)) {
            $lead = $team->leads()->create([
                'company_name' => $this->companyName($conversation, $inbound),
                'contact_name' => $conversation->contact_name,
                'phone' => $conversation->contact_number,
                'source' => 'whatsapp',
                'potential_project' => Str::limit($text, self::MAX_PROJECT_LENGTH, ''),
                'status' => LeadStatus::New,
                // Free text by design (leads.next_follow_up), and the value the lead
                // form itself suggests.
                'next_follow_up' => 'Besok',
            ]);

            $conversation->lead_id = $lead->getKey();
        }

        $conversation->state = WhatsAppConversationState::Completed;

        return sprintf(
            'Terima kasih, %s. Tim %s akan menghubungi Anda segera.',
            $conversation->contact_name ?? 'Bapak/Ibu',
            $team->name,
        );
    }

    /**
     * A lead needs a company name; fall back to what the conversation does have.
     */
    private function companyName(WhatsAppConversation $conversation, WhatsAppMessage $inbound): string
    {
        return $conversation->company_name
            ?? $conversation->contact_name
            ?? $inbound->from_number;
    }

    /**
     * Keep the outbound answer in the same thread as the question.
     */
    private function recordOutbound(Team $team, WhatsAppMessage $inbound, string $reply, ?string $messageId): void
    {
        $team->whatsappMessages()->create([
            'direction' => WhatsAppMessageDirection::Outbound,
            'wamid' => $messageId,
            'from_number' => $inbound->to_number ?? $team->whatsapp_phone_number_id ?? '',
            'to_number' => $inbound->from_number,
            'type' => 'text',
            'body' => $reply,
            'status' => $messageId === null ? 'logged' : 'sent',
        ]);
    }
}
