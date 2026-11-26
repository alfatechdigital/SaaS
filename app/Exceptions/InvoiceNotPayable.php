<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

/**
 * Thrown when a tenant asks to pay an invoice that is not waiting for money.
 *
 * A paid invoice must not produce a second transaction, and a voided one no longer
 * describes a debt. Both are refused here rather than reported by the provider
 * later, because a duplicate charge is much harder to undo than a 409.
 */
final class InvoiceNotPayable extends RuntimeException
{
    public static function for(Invoice $invoice): self
    {
        return new self(sprintf(
            'Invoice [%s] cannot be paid while it is [%s].',
            $invoice->number,
            $invoice->status->value,
        ));
    }
}
