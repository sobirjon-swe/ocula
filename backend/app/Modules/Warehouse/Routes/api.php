<?php

declare(strict_types=1);

use App\Modules\Warehouse\Http\Controllers\PurchaseController;
use App\Modules\Warehouse\Http\Controllers\StockController;
use App\Modules\Warehouse\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouse — /api/v1
|--------------------------------------------------------------------------
|
| Ombor holatini o'zgartiradigan amallar `idempotency` middleware ostida:
| filialda internet uzilib qayta yuborilganda ikkinchi marta kirim
| qilinmasin (§9, ANALIZ 3.7).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::apiResource('suppliers', SupplierController::class);

    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');

    Route::get('stock/balances', [StockController::class, 'balances'])->name('stock.balances');
    Route::get('stock/variants/{variant}/locations/{location}', [StockController::class, 'balanceOf'])
        ->name('stock.balance-of');
    Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
            ->name('purchases.receive');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
            ->name('purchases.cancel');

        Route::post('stock/movements/{movement}/reverse', [StockController::class, 'reverse'])
            ->name('stock.reverse');
    });
});
