<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)
    ->name('telegram.webhook');