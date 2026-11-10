<?php

use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Third-party webhooks
|--------------------------------------------------------------------------
|
| Registered outside the `web` middleware group: these requests come from
| providers, not browsers, so they carry no session and no CSRF token. Every
| route proves its own authenticity instead — never add `auth` here, because the
| provider cannot log in.
|
*/

Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');

Route::post('whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware(VerifyWhatsAppSignature::class)
    ->name('webhooks.whatsapp.receive');
