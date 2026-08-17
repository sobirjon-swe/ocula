<?php

declare(strict_types=1);

use App\Modules\Delivery\Http\Controllers\CashCollectionController;
use App\Modules\Delivery\Http\Controllers\DriverBalanceController;
use App\Modules\Delivery\Http\Controllers\TripController;
use App\Modules\Delivery\Http\Controllers\TripStopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery — /api/v1
|--------------------------------------------------------------------------
|
| Pulga tegadigan `POST` lar (`deliver`, `collections`) `idempotency`
| ostida: internetsiz filialda qayta yuborilganda ikkinchi to'lov yoki
| ikkinchi inkassatsiya yozilmasin (§9).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');

    Route::get('driver-balances', [DriverBalanceController::class, 'index'])->name('driver-balances.index');
    Route::get('driver-balances/{driver}', [DriverBalanceController::class, 'show'])->name('driver-balances.show');

    Route::get('collections', [CashCollectionController::class, 'index'])->name('collections.index');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('trips', [TripController::class, 'store'])->name('trips.store');
        Route::post('trips/{trip}/stops', [TripController::class, 'addStop'])->name('trips.stops.store');
        Route::post('trips/{trip}/start', [TripController::class, 'start'])->name('trips.start');
        Route::post('trips/{trip}/finish', [TripController::class, 'finish'])->name('trips.finish');
        Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');

        Route::post('trip-stops/{stop}/deliver', [TripStopController::class, 'deliver'])
            ->name('trip-stops.deliver');
        Route::post('trip-stops/{stop}/fail', [TripStopController::class, 'fail'])
            ->name('trip-stops.fail');

        Route::post('collections', [CashCollectionController::class, 'store'])->name('collections.store');
    });
});
