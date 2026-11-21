<?php

namespace App\Notifications\Teams;

use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message submitted through the tenant's public contact form.
 *
 * Sent to the tenant owner immediately (not queued): the form is the only way a
 * visitor can reach a Basic tenant, so losing it because a worker is down would
 * lose a real customer inquiry. Tenants without `lead_management` have nowhere
 * to store the message, which makes this email the only copy.
 */
class ContactMessageReceived extends Notification
{
    public function __construct(
        public readonly Team $team,
        public readonly string $name,
        public readonly string $contact,
        public readonly string $message,
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
        return (new MailMessage)
            ->subject(__('Pesan kontak baru dari :name', ['name' => $this->name]))
            ->greeting(__('Pesan baru di halaman publik :team', ['team' => $this->team->name]))
            ->line(__('Nama: :name', ['name' => $this->name]))
            ->line(__('Kontak: :contact', ['contact' => $this->contact]))
            ->line(__('Pesan:'))
            ->line($this->message)
            ->action(
                __('Lihat halaman publik'),
                route('public.company-profile', ['team' => $this->team->slug]),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'name' => $this->name,
            'contact' => $this->contact,
            'message' => $this->message,
        ];
    }
}
