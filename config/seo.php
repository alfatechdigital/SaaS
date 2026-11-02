<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitemap Ping
    |--------------------------------------------------------------------------
    |
    | Every tenant's public page and `sitemap.xml` are derived from its own data,
    | so a change in the app is the only signal a crawler can get. When a public
    | page changes, `App\Jobs\PingSitemapJob` notifies the endpoints below.
    |
    | The endpoint list is deliberately empty by default. Google and Bing retired
    | anonymous sitemap pings in 2023 (the old `/ping?sitemap=` endpoints answer
    | 404), and the surviving paths — Search Console, IndexNow — need per-site
    | credentials that belong to the deployment, not to this repository. Set
    | `SEO_SITEMAP_PING_URLS` (comma separated) once such an endpoint exists;
    | `{sitemap}` is replaced with the tenant's URL-encoded sitemap URL.
    |
    */

    'ping' => [
        'enabled' => env('SEO_SITEMAP_PING_ENABLED', true),

        'urls' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SEO_SITEMAP_PING_URLS', '')),
        ))),

        'timeout' => (int) env('SEO_SITEMAP_PING_TIMEOUT', 5),
    ],

];
