<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the account & system preferences overview.
     *
     * The React template showed a "Cloud Firestore connection" panel here. There
     * is no Firestore any more, so that panel now reports the actual stack the
     * app runs on instead of a status that would be a lie.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/settings/Index', [
            'system' => [
                'database' => (string) config('database.default'),
                'cache' => (string) config('cache.default'),
                'queue' => (string) config('queue.default'),
                'session' => (string) config('session.driver'),
                'mail' => (string) config('mail.default'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ],
        ]);
    }
}
