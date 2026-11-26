<?php

namespace App\Enums;

/**
 * Lifecycle of one tenant's subscription (B-2, tugas 17 Nov).
 *
 * The diagram is the one agreed in
 * docs/implementation/phase-04-plan-dan-entitlement.md §4.5.3:
 *
 *     trialing ──bayar──▶ active ──gagal bayar──▶ past_due ──(+7 hari)──▶ expired
 *         │                 │                         │
 *         └──batal──▶ canceled ◀──minta batal─────────┘
 *
 * Two deliberate absences:
 *
 * - **No `suspended`.** Platform suspension is a property of the tenant
 *   (`teams.suspended_at`, Fase 2) and must not be mixed into the billing
 *   lifecycle; otherwise lifting one would silently lift the other.
 * - **No `pending`.** A row exists only once there is a period to charge for, so
 *   every row is already in one of these states.
 *
 * `trialing → expired` is the trial that ends without a payment.
 *
 * Moving a subscription is **not** done here: persisting a transition (periods,
 * `canceled_at`, `ended_at`, invoices) is the subscription service's job (tugas
 * 18 Nov). This enum owns only the rules that service must obey.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Masa uji coba',
            self::Active => 'Aktif',
            self::PastDue => 'Menunggak',
            self::Canceled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /**
     * The statuses this one may move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::Canceled, self::Expired],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::Canceled, self::Expired],
            self::Canceled, self::Expired => [],
        };
    }

    /**
     * Whether the machine permits moving from this status to the given one.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Whether nothing follows this status.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Whether the tenant may still use its features in this status.
     *
     * `past_due` still grants access: §B.4.3 gives a 7-day grace period with three
     * reminders before the tenant is suspended, so cutting features on the first
     * failed charge would punish a tenant whose card simply expired.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            self::Canceled, self::Expired => false,
        };
    }
}
