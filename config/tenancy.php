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

];
