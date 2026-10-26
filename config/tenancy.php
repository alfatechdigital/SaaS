<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | Slot for resolving a tenant by host (`{slug}.base-domain`) instead of by
    | path. Host resolution is deferred to wave 2 (ADR-04), so nothing in the
    | application reads this key yet and the default is `null`. Filling it in
    | must not change behaviour on its own.
    |
    */

    'base_domain' => env('TENANCY_BASE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Subdomain Enabled
    |--------------------------------------------------------------------------
    |
    | Companion switch for the host-based resolver. `null` means "not decided";
    | code must not treat it as `false`.
    |
    */

    'subdomain_enabled' => env('TENANCY_SUBDOMAIN_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Preference Defaults
    |--------------------------------------------------------------------------
    |
    | Values used when a tenant has not stored its own preference yet. Read them
    | through `$team->setting('locale')` — never straight from this array — so a
    | tenant override and the default behave the same way for callers.
    |
    | Only preferences something actually reads belong here. Branding, logo and
    | similar product features are not defaults; they are features.
    |
    */

    'defaults' => [
        'locale' => 'id',
        'timezone' => 'Asia/Jakarta',
        'currency' => 'IDR',
    ],

];
