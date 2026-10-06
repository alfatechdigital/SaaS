<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Directory
    |--------------------------------------------------------------------------
    |
    | Where `php artisan db:backup` writes its dumps. Kept outside the public
    | disk on purpose: a database dump holds every tenant's data, so it must
    | never be reachable over HTTP.
    |
    */

    'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | After a successful backup, dumps older than this many days are deleted.
    | Overridable per run with `db:backup --keep-days=`.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 7),

];
