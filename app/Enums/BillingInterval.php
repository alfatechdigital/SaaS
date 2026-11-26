<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How often a subscription is billed (PDR-05: monthly or yearly).
 *
 * The interval decides the length of a paid period, so it is stored on the
 * subscription itself rather than re-derived from a price: a tenant that paid
 * yearly must keep a twelve-month period even if the monthly price changes.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    /**
     * The end of one period that starts at the given moment.
     *
     * `NoOverflow` keeps the calendar honest: a period starting on 31 January
     * ends on 28/29 February instead of spilling into March.
     */
    public function advance(CarbonInterface $start): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => CarbonImmutable::instance($start)->addMonthNoOverflow(),
            self::Yearly => CarbonImmutable::instance($start)->addYearNoOverflow(),
        };
    }
}
