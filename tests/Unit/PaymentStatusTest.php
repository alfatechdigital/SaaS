<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use Tests\TestCase;

/**
 * Provider statuses are translated by each driver; the rest of the application only
 * ever sees these five, and only `Paid` may settle an invoice.
 */
class PaymentStatusTest extends TestCase
{
    public function test_only_a_paid_status_settles_an_invoice(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $this->assertSame(
                $status === PaymentStatus::Paid,
                $status->settlesInvoice(),
                "Status [{$status->value}] must not be treated as money received.",
            );
        }
    }

    public function test_every_status_has_a_distinct_label(): void
    {
        $labels = array_map(
            static fn (PaymentStatus $status): string => $status->label(),
            PaymentStatus::cases(),
        );

        $this->assertSame($labels, array_unique($labels));
    }
}
