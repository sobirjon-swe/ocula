<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\CashController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance — /api/v1
|--------------------------------------------------------------------------
|
| Kassaga tegadigan `POST` lar `idempotency` middleware ostida: filialda
| internet uzilib qayta yuborilganda pul ikki marta yozilmasin (§9).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('cash/movements', [CashController::class, 'index'])->name('cash.movements');
    Route::get('cash/summary', [CashController::class, 'summary'])->name('cash.summary');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('cash/movements', [CashController::class, 'store'])->name('cash.store');
        Route::post('cash/movements/{movement}/reverse', [CashController::class, 'reverse'])
            ->name('cash.reverse');
    });
});
