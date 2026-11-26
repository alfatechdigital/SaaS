<?php

namespace App\Notifications\Billing;

use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder that a subscription invoice is still unpaid (B-2, tugas 19 Nov).
 *
 * Sent directly rather than queued: §B.4.3 promises three reminders before the
 * tenant is cut off, and a reminder lost to a dead worker would break that
 * promise silently. There is no billing page to link to yet (UI lands 26 Nov), so
 * the message carries everything the owner needs: number, amount, and deadline.
 */
class SubscriptionPaymentReminder extends Notification
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly Team $team,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $graceDeadline = $this->invoice->due_at->addDays((int) config('billing.grace_days'));

        return (new MailMessage)
            ->subject(__('Pengingat pembayaran langganan :number', ['number' => $this->invoice->number]))
            ->greeting(__('Langganan :team belum dibayar', ['team' => $this->team->name]))
            ->line(__('Invoice: :number', ['number' => $this->invoice->number]))
            ->line(__('Jumlah: Rp:amount', ['amount' => number_format($this->invoice->amount, 0, ',', '.')]))
            ->line(__('Jatuh tempo: :date', ['date' => $this->invoice->due_at->translatedFormat('j F Y')]))
            ->line(__('Akses fitur tetap aktif sampai :date.', ['date' => $graceDeadline->translatedFormat('j F Y')]))
            ->line(__('Setelah tanggal itu langganan berakhir otomatis dan data disimpan sesuai kebijakan retensi.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'team_id' => $this->team->id,
            'amount' => $this->invoice->amount,
        ];
    }
}
