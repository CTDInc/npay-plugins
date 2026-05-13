<?php

use Illuminate\Support\Facades\Route;
use NPay\Laravel\Http\Controllers\WebhookController;

Route::post(
    config('npay.webhook_route', 'npay/webhook'),
    WebhookController::class
)
    ->middleware('npay.webhook')
    ->name('npay.webhook');
