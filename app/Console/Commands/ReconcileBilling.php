<?php

namespace App\Console\Commands;

use App\Support\Billing\InvoiceReconciler;
use Illuminate\Console\Command;

/**
 * Pulls the status of invoices that a payment link exists for (B-2, tugas 25 Nov).
 *
 * Runs from `routes/console.php` an hour after the period check: the daily clock
 * decides what is owed, reconciliation decides what was already paid. Nothing here
 * runs inside a tenant context — the reconciler opens one per invoice.
 */
class ReconcileBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cocokkan invoice terbuka dengan status transaksi provider (webhook yang terlewat)';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceReconciler $reconciler): int
    {
        if (config('billing.driver') === 'none') {
            $this->components->warn('BILLING_DRIVER=none: tidak ada provider yang bisa ditanyai.');

            return self::SUCCESS;
        }

        $report = $reconciler->reconcile();

        $this->components->info($report->summary());

        return self::SUCCESS;
    }
}
