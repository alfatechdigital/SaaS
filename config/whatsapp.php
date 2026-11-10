<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | `log` writes the message to the application log and sends nothing — the
    | default, so a fresh clone or a test run can never message a real person.
    | `meta` talks to the WhatsApp Cloud API using the tenant's own credentials.
    |
    | Production must set WHATSAPP_DRIVER=meta.
    |
    */

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Graph API
    |--------------------------------------------------------------------------
    |
    | Cloud API version is pinned rather than floating: Meta deprecates versions
    | on a schedule, and an upgrade should be a deliberate change with a test run,
    | not something that happens when the process restarts.
    |
    */

    'graph' => [
        'base_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | `queue` names the queue the sending job uses, so WhatsApp traffic can be
    | given its own worker instead of queueing behind slower tenant work.
    |
    */

    'queue' => env('WHATSAPP_QUEUE', 'default'),

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),

];
