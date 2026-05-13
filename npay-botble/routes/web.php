<?php

use Botble\NPay\Http\Controllers\NPayController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Botble\NPay\Http\Controllers', 'middleware' => ['web']], function () {
    Route::group(['prefix' => 'npay', 'as' => 'npay.'], function () {
        Route::post('payment', [NPayController::class, 'postPayment'])->name('payment');
        Route::get('display/{payment}', [NPayController::class, 'display'])->name('display');
    });
});
