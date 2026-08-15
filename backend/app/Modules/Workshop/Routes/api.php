<?php

declare(strict_types=1);

use App\Modules\Workshop\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workshop — /api/v1
|--------------------------------------------------------------------------
|
| `POST /work-orders` **yo'q**: buyruq buyurtma ustaxonaga o'tganda
| tizim tomonidan tug'iladi (§6.6).
|
| Omborga tegadigan amallar (`consume`, `defect`) `idempotency`
| ostida: planshetda internet uzilib qayta yuborilganda material ikki
| marta hisobdan chiqmasin (§9).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('work-orders/board', [WorkOrderController::class, 'board'])->name('work-orders.board');
    Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');

    Route::put('work-orders/{workOrder}/master', [WorkOrderController::class, 'assign'])
        ->name('work-orders.assign');
    Route::put('work-orders/{workOrder}/priority', [WorkOrderController::class, 'setPriority'])
        ->name('work-orders.priority');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('work-orders/{workOrder}/start', [WorkOrderController::class, 'start'])
            ->name('work-orders.start');
        Route::post('work-orders/{workOrder}/finish', [WorkOrderController::class, 'finish'])
            ->name('work-orders.finish');
        Route::post('work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])
            ->name('work-orders.cancel');
        Route::post('work-orders/{workOrder}/consume', [WorkOrderController::class, 'consume'])
            ->name('work-orders.consume');
        Route::post('work-orders/{workOrder}/defect', [WorkOrderController::class, 'defect'])
            ->name('work-orders.defect');
    });
});
