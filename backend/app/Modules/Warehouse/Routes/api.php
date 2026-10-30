<?php

declare(strict_types=1);

use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Http\Controllers\DefectController;
use App\Modules\Warehouse\Http\Controllers\InventoryController;
use App\Modules\Warehouse\Http\Controllers\LostSaleController;
use App\Modules\Warehouse\Http\Controllers\MasterStockController;
use App\Modules\Warehouse\Http\Controllers\PurchaseController;
use App\Modules\Warehouse\Http\Controllers\StockController;
use App\Modules\Warehouse\Http\Controllers\StockRequestController;
use App\Modules\Warehouse\Http\Controllers\SupplierController;
use App\Modules\Warehouse\Http\Controllers\SupplierReturnController;
use App\Modules\Warehouse\Http\Controllers\TransferController;
use App\Modules\Warehouse\Http\Controllers\TransferReceiptController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
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
    Route::get('supplier-returns', [SupplierReturnController::class, 'index']);
    Route::post('supplier-returns/{supplierReturn}/post', [SupplierReturnController::class, 'post'])->middleware('idempotency');
    Route::get('locations', function (Request $request) {
        abort_unless($request->user()?->can('warehouse.stock.view') || $request->user()?->can('warehouse.inventory.count'), 403);

        return ApiResponse::data(Location::query()->orderBy('id')->get()->toArray());
    });
    Route::get('inventories', [InventoryController::class, 'index']);
    Route::get('inventories/{inventory}', [InventoryController::class, 'show']);
    Route::put('inventories/{inventory}/counts', [InventoryController::class, 'counts']);
    Route::middleware('idempotency')->group(function (): void {
        Route::post('inventories', [InventoryController::class, 'store']);
        Route::post('inventories/{inventory}/review', [InventoryController::class, 'review']);
        Route::post('inventories/{inventory}/complete', [InventoryController::class, 'complete']);
        Route::post('inventories/{inventory}/cancel', [InventoryController::class, 'cancel']);
    });
    Route::apiResource('suppliers', SupplierController::class);

    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');

    Route::get('stock/balances', [StockController::class, 'balances'])->name('stock.balances');
    Route::get('stock/variants/{variant}/locations/{location}', [StockController::class, 'balanceOf'])
        ->name('stock.balance-of');
    Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::get('transfers/{transfer}/taxi-receipt', TransferReceiptController::class);

    Route::get('defects', [DefectController::class, 'index'])->name('defects.index');
    Route::post('defects', [DefectController::class, 'store'])->middleware('idempotency');
    Route::get('defects/{defect}', [DefectController::class, 'show'])->name('defects.show');

    Route::get('lost-sales', [LostSaleController::class, 'index'])->name('lost_sales.index');
    Route::post('lost-sales', [LostSaleController::class, 'store'])->name('lost_sales.store');

    // Usta zaxirasi — `master` turidagi location qoldig'i (§6.6).
    Route::get('master-stock', [MasterStockController::class, 'index'])->name('master-stock.index');

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

        Route::post('master-stock', [MasterStockController::class, 'store'])
            ->name('master-stock.store');
        Route::post('defects/{defect}/approve', [DefectController::class, 'approve'])
            ->name('defects.approve');

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
