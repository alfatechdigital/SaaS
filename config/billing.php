<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Midtrans bills one transaction at a time and knows nothing about a
    | subscription lifecycle (D-05), so the provider only charges the invoice it
    | is handed; `subscriptions` stays the source of truth. The value is copied
    | onto each subscription row, so switching providers never rewrites history.
    |
    */

    'provider' => env('BILLING_PROVIDER', 'midtrans'),

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    |
    | The three durations of §B.4.3 — a 30-day Basic trial, a 7-day grace period
    | in `past_due`, and 30 days of data retention after a subscription ends.
    | They are product rules rather than deployment settings, hence plain values:
    | changing one is a decision, not a configuration tweak.
    |
    */

    'trial_days' => 30,
    'grace_days' => 7,
    'retention_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Dunning
    |--------------------------------------------------------------------------
    |
    | §B.4.3 promises three reminders inside the 7-day grace period. Days are
    | counted from the invoice's due date (the end of the paid period): 0, 3, 6 —
    | the last one lands a day before the subscription expires, never after.
    |
    */

    'dunning' => [
        'days' => [0, 3, 6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prices
    |--------------------------------------------------------------------------
    |
    | Whole rupiah, no subunit (§B.4.3). `config/plans.php` remains the source of
    | entitlement; this file owns what the tenant pays, and `PlanSeeder` copies
    | both into the `plans` table.
    |
    | Yearly is ten monthly payments — two months free, as decided in PDR-05.
    |
    */

    'plans' => [

        'basic' => [
            'description' => 'Profil perusahaan, portofolio, SEO dasar, dan form kontak umum.',
            'price_monthly' => 149000,
            'price_yearly' => 1490000,
        ],

        'plus' => [
            'description' => 'Basic plus domain kustom, WhatsApp bot, payment gateway, dan lead management.',
            'price_monthly' => 349000,
            'price_yearly' => 3490000,
        ],

        'pro' => [
            'description' => 'Semua fitur Plus, ditambah modul operasional, SEO lanjutan, Content AI, dan role management.',
            'price_monthly' => 799000,
            'price_yearly' => 7990000,
        ],

    ],

];
