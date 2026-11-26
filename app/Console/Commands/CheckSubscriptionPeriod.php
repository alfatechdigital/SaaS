<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Support\Billing\SubscriptionPeriodChecker;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The daily clock of the subscription lifecycle (B-2, tugas 19 Nov).
 *
 * Runs from `routes/console.php`. Only rows whose period has actually ended are
 * touched, so a quiet day costs one indexed query.
 *
 * Every row is processed through {@see SubscriptionPeriodChecker}, which opens its
 * own tenant context — the command itself must not run any tenant-scoped query
 * (Fase 2, tugas 2.6.4).
 */
class CheckSubscriptionPeriod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-period';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tutup periode langganan yang berakhir, terbitkan invoice, dan kirim pengingat';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionPeriodChecker $checker): int
    {
        $now = CarbonImmutable::now();

        $due = Subscription::query()
            ->withoutTeamScope()
            ->current()
            ->with('team')
            ->where('current_period_end', '<=', $now)
            ->get();

        foreach ($due as $subscription) {
            $checker->check($subscription, $now);
        }

        $this->components->info(sprintf('%d langganan diperiksa.', $due->count()));

        return self::SUCCESS;
    }
}
