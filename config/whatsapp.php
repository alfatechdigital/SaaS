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

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | `verify_token` is echoed back during Meta's subscription handshake; it is a
    | shared secret for a one-time URL ownership check, not a password.
    |
    | `app_secret` signs the body of every notification (X-Hub-Signature-256). Both
    | belong to the single Meta app that all tenants connect their numbers to, so
    | they are deployment configuration — unlike the per-tenant credentials stored
    | on `teams`. Leave them empty and the webhook refuses every request rather
    | than accepting unsigned traffic.
    |
    */

    'webhook' => [
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Abuse Guards
    |--------------------------------------------------------------------------
    |
    | `rate_limit.per_minute` caps how fast one tenant may send, so a broken loop
    | or a compromised credential cannot drain a number's reputation in a minute.
    | The monthly quota itself lives in `config/plans.php` — it is a plan feature,
    | not a transport setting.
    |
    | `service_window.hours` is Meta's 24-hour rule: free text is only allowed
    | within a day of the customer's last message. Outside it, an approved
    | template is mandatory, and `template.language` is the default to request.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('WHATSAPP_RATE_LIMIT_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monthly Allowance for Add-on Tenants
    |--------------------------------------------------------------------------
    |
    | A plan whose feature list does not include `whatsapp_bot` reports a quota of
    | zero, because the allowance is a plan limit. But §B.4.2 lets a tenant buy the
    | bot as an add-on on any plan — and an add-on that grants a feature with no
    | allowance would be a paid feature that cannot send a single message. Tenants
    | in that position get the allowance below instead.
    |
    */

    'quota' => [
        'addon_allowance' => (int) env('WHATSAPP_ADDON_ALLOWANCE', 500),
    ],

    'service_window' => [
        'hours' => (int) env('WHATSAPP_SERVICE_WINDOW_HOURS', 24),
    ],

    'template' => [
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'id'),
    ],

];
