<?php

declare(strict_types=1);

use App\Modules\Warehouse\Http\Controllers\PurchaseController;
use App\Modules\Warehouse\Http\Controllers\StockController;
use App\Modules\Warehouse\Http\Controllers\StockRequestController;
use App\Modules\Warehouse\Http\Controllers\SupplierController;
use App\Modules\Warehouse\Http\Controllers\TransferController;
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

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');

    Route::get('stock-requests', [StockRequestController::class, 'index'])->name('stock-requests.index');
    Route::get('stock-requests/{stockRequest}', [StockRequestController::class, 'show'])
        ->name('stock-requests.show');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
            ->name('purchases.receive');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
            ->name('purchases.cancel');

        Route::post('stock/movements/{movement}/reverse', [StockController::class, 'reverse'])
            ->name('stock.reverse');

        // Transferning har bosqichi alohida ruxsat talab qiladi:
        // jo'natgan odam o'zi qabul qilib qo'ymasin (7.4).
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('transfers/{transfer}/send', [TransferController::class, 'send'])
            ->name('transfers.send');
        Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])
            ->name('transfers.receive');
        Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])
            ->name('transfers.cancel');
        Route::post('transfers/{transfer}/resolve-discrepancy', [TransferController::class, 'resolveDiscrepancy'])
            ->name('transfers.resolve-discrepancy');

        Route::post('stock-requests', [StockRequestController::class, 'store'])
            ->name('stock-requests.store');
        Route::post('stock-requests/{stockRequest}/approve', [StockRequestController::class, 'approve'])
            ->name('stock-requests.approve');
        Route::post('stock-requests/{stockRequest}/reject', [StockRequestController::class, 'reject'])
            ->name('stock-requests.reject');
        Route::post('stock-requests/{stockRequest}/cancel', [StockRequestController::class, 'cancel'])
            ->name('stock-requests.cancel');
        Route::post('stock-requests/{stockRequest}/fulfill', [StockRequestController::class, 'fulfill'])
            ->name('stock-requests.fulfill');
    });
});
