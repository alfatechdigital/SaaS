<?php

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use Tests\TestCase;

/**
 * Guards the subscription state machine (B-2, tugas 17 Nov).
 *
 * The transition map here is a transcription of the diagram in
 * docs/implementation/phase-04-plan-dan-entitlement.md §4.5.3. It is written out
 * again rather than derived from the enum so a change to the enum cannot pass by
 * changing its own expectation too.
 */
class SubscriptionStatusTest extends TestCase
{
    /**
     * The agreed diagram, status value → statuses it may move to.
     *
     * @return array<string, array<int, string>>
     */
    private function documentedTransitions(): array
    {
        return [
            'trialing' => ['active', 'canceled', 'expired'],
            'active' => ['past_due', 'canceled'],
            'past_due' => ['active', 'canceled', 'expired'],
            'canceled' => [],
            'expired' => [],
        ];
    }

    public function test_the_map_covers_every_status(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(SubscriptionStatus::cases(), 'value'),
            array_keys($this->documentedTransitions()),
        );
    }

    public function test_each_status_only_moves_where_the_diagram_allows(): void
    {
        $documented = $this->documentedTransitions();

        foreach (SubscriptionStatus::cases() as $from) {
            foreach (SubscriptionStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to->value, $documented[$from->value], true),
                    $from->canTransitionTo($to),
                    "[{$from->value}] → [{$to->value}] does not match the agreed diagram.",
                );
            }
        }
    }

    public function test_canceled_and_expired_are_final(): void
    {
        $this->assertTrue(SubscriptionStatus::Canceled->isFinal());
        $this->assertTrue(SubscriptionStatus::Expired->isFinal());

        $this->assertFalse(SubscriptionStatus::Trialing->isFinal());
        $this->assertFalse(SubscriptionStatus::Active->isFinal());
        $this->assertFalse(SubscriptionStatus::PastDue->isFinal());
    }

    public function test_access_is_granted_while_billing_is_live(): void
    {
        // `past_due` is still inside the 7-day grace period of §B.4.3.
        $this->assertTrue(SubscriptionStatus::Trialing->grantsAccess());
        $this->assertTrue(SubscriptionStatus::Active->grantsAccess());
        $this->assertTrue(SubscriptionStatus::PastDue->grantsAccess());

        $this->assertFalse(SubscriptionStatus::Canceled->grantsAccess());
        $this->assertFalse(SubscriptionStatus::Expired->grantsAccess());
    }

    public function test_every_status_is_reachable_from_a_trial(): void
    {
        $documented = $this->documentedTransitions();
        $seen = [SubscriptionStatus::Trialing->value => true];
        $queue = [SubscriptionStatus::Trialing->value];

        while ($queue !== []) {
            $from = array_shift($queue);

            foreach ($documented[$from] as $to) {
                if (! isset($seen[$to])) {
                    $seen[$to] = true;
                    $queue[] = $to;
                }
            }
        }

        $this->assertEqualsCanonicalizing(
            array_column(SubscriptionStatus::cases(), 'value'),
            array_keys($seen),
            'A status is unreachable from trialing, so the machine has a dead branch.',
        );
    }

    public function test_every_status_has_a_distinct_label(): void
    {
        $labels = array_map(
            static fn (SubscriptionStatus $status): string => $status->label(),
            SubscriptionStatus::cases(),
        );

        $this->assertSame($labels, array_unique($labels));
    }
}
