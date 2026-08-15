<?php

declare(strict_types=1);

use App\Modules\Sales\Http\Controllers\CustomerController;
use App\Modules\Sales\Http\Controllers\OrderController;
use App\Modules\Sales\Http\Controllers\OrderReturnController;
use App\Modules\Sales\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales — /api/v1
|--------------------------------------------------------------------------
|
| Ombor yoki kassaga tegadigan barcha `POST` lar `idempotency`
| middleware ostida: filialda internet uzilib qayta yuborilganda ikkinchi
| chek, ikkinchi to'lov yoki ikkinchi qaytarish yaratilmasin (§9).
|
| `returns` yo'li `{return}` parametrini ishlatmaydi — u `Return` klass
| nomiga o'xshab chalkashtiradi; model `OrderReturn` deb nomlangan.
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('returns', [OrderReturnController::class, 'index'])->name('returns.index');
    Route::get('returns/{return}', [OrderReturnController::class, 'show'])->name('returns.show');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');

        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])
            ->name('orders.deliver');
        Route::post('orders/{order}/status', [OrderController::class, 'changeStatus'])
            ->name('orders.status');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');

        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
            ->name('orders.payments.store');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])
            ->name('payments.reverse');

        Route::post('orders/{order}/returns', [OrderReturnController::class, 'store'])
            ->name('orders.returns.store');
    });
});
