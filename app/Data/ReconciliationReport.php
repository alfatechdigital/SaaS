<?php

namespace App\Data;

/**
 * What one reconciliation pass did (B-2, tugas 25 Nov).
 *
 * Counted rather than collected: the command prints one line, and the stored
 * evidence lives in the normal application log. `failed` is the only number that
 * asks for attention — the rest is the pass working as intended.
 */
final class ReconciliationReport
{
    /** Invoices with a payment link that were asked about. */
    public int $checked = 0;

    /** Provider said paid, and the invoice was still open. */
    public int $settled = 0;

    /** Provider said failed/expired, and the invoice was still open. */
    public int $closed = 0;

    /** Provider still has no decision: nothing to apply yet. */
    public int $pending = 0;

    /** Decision already applied earlier (or not ours to apply). */
    public int $unchanged = 0;

    /** The provider could not be asked; the next pass will try again. */
    public int $failed = 0;

    public function summary(): string
    {
        return sprintf(
            '%d invoice diperiksa: %d lunas, %d ditutup, %d masih menunggu, %d tidak berubah, %d gagal.',
            $this->checked,
            $this->settled,
            $this->closed,
            $this->pending,
            $this->unchanged,
            $this->failed,
        );
    }
}
