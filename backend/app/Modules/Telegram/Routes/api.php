<?php

declare(strict_types=1);

use App\Modules\Telegram\Http\Controllers\CustomerLinkController;
use App\Modules\Telegram\Http\Controllers\DebtReminderController;
use App\Modules\Telegram\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram — /api/v1
|--------------------------------------------------------------------------
|
| `webhook` ochiq: Telegram serveri Sanctum token bilan kelmaydi, o'rniga
| sekret sarlavha tekshiriladi (BOSQICH-7.md §5 #5).
|
*/

Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('api.telegram.webhook');

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::post('customers/{customer}/telegram-link-code', [CustomerLinkController::class, 'store'])
        ->name('customers.telegram-link-code');

    Route::post('orders/{order}/debt-reminder', [DebtReminderController::class, 'store'])
        ->name('orders.debt-reminder');
});
