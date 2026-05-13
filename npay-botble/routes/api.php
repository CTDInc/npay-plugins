<?php

use Botble\NPay\Http\Controllers\NPayController;
use Botble\NPay\Http\Controllers\NPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Botble\NPay\Http\Controllers', 'middleware' => ['api']], function () {
    Route::post('api/webhooks/npay', [NPayWebhookController::class, 'handle'])->name('npay.webhook');

    Route::get('api/npay/status/{payment}', [NPayController::class, 'status'])->name('npay.status');
});
