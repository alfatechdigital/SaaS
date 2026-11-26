<?php

namespace App\Support\Billing;

use App\Data\ReconciliationReport;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Catches payments whose webhook never arrived (B-2, tugas 25 Nov).
 *
 * A webhook is the fast path, not a guarantee: Midtrans retries for a while, but
 * a deployment, a DNS change or a rejected notification can outlast the retries.
 * The tenant has paid and the invoice still says `open` — the worst possible
 * disagreement, because dunning keeps chasing money that already arrived.
 *
 * Only invoices that a payment link exists for are asked about: without a link
 * there is no transaction at the provider, so there is nothing to learn. The
 * window (`billing.reconciliation.window_days`) bounds the work to links that
 * could still be in flight; older invoices belong to manual follow-up.
 *
 * Reading is not applying: every decision goes through {@see PaymentApplication},
 * exactly like a webhook would, so a late webhook and a reconcile pass cannot
 * disagree or double-charge a period.
 */
final class InvoiceReconciler
{
    public function __construct(
        private readonly InvoicePayment $payments,
        private readonly PaymentApplication $application,
    ) {}

    public function reconcile(?CarbonImmutable $now = null): ReconciliationReport
    {
        $report = new ReconciliationReport;

        $invoices = $this->candidates($now ?? CarbonImmutable::now());

        foreach ($invoices as $invoice) {
            $report->checked++;

            try {
                $result = $this->payments->refresh($invoice);
            } catch (PaymentGatewayError $exception) {
                // One unreachable order must not stop the pass; tomorrow's run
                // tries again and the log holds the reason.
                $report->failed++;

                Log::warning('Reconciliation could not read a transaction status.', [
                    'invoice' => $invoice->number,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($result->status === PaymentStatus::Pending) {
                $report->pending++;

                continue;
            }

            $wasOpen = $invoice->isOpen();

            $this->application->apply($invoice, $result->status, $result->transactionId, 'reconciliation');

            switch ($result->status) {
                case PaymentStatus::Paid:
                    if ($wasOpen) {
                        $report->settled++;
                    } else {
                        $report->unchanged++;
                    }
                    break;

                case PaymentStatus::Failed:
                case PaymentStatus::Expired:
                    if ($wasOpen) {
                        $report->closed++;
                    } else {
                        $report->unchanged++;
                    }
                    break;

                default:
                    // Refund/chargeback is logged for manual follow-up, not applied.
                    $report->unchanged++;
                    break;
            }
        }

        return $report;
    }

    /**
     * Open invoices with a payment link that could still be in flight.
     *
     * `updated_at` is the right clock, not `created_at`: the link is written when
     * the tenant starts paying, so a yearly invoice issued months ago is included
     * as long as its link is recent.
     *
     * @return Collection<int, Invoice>
     */
    private function candidates(CarbonImmutable $now): Collection
    {
        $since = $now->subDays(max(1, (int) config('billing.reconciliation.window_days', 30)));

        return Invoice::query()
            ->withoutTeamScope()
            ->where('status', InvoiceStatus::Open->value)
            ->whereNotNull('payment_url')
            ->where('updated_at', '>=', $since)
            ->orderBy('updated_at')
            ->limit(max(1, (int) config('billing.reconciliation.batch', 100)))
            ->get();
    }
}
