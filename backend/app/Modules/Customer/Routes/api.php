<?php

declare(strict_types=1);

use App\Modules\Customer\Http\Controllers\AuthController;
use App\Modules\Customer\Http\Controllers\DebtController;
use App\Modules\Customer\Http\Controllers\DeliveryConfirmationController;
use App\Modules\Customer\Http\Controllers\OrderController;
use App\Modules\Customer\Http\Controllers\PrescriptionController;
use App\Modules\Customer\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer — /api/v1/customer
|--------------------------------------------------------------------------
|
| `auth/telegram` ochiq — shu orqali token olinadi. Qolgani `auth:customer`
| ostida (BOSQICH-9.md §3), `web` guard'dagi xodim tokeni bu yerga
| kirolmaydi — ikkalasi butunlay mustaqil.
|
*/

Route::prefix('customer')->name('api.customer.')->group(function (): void {
    Route::post('auth/telegram', [AuthController::class, 'telegram'])->name('auth.telegram');

    Route::middleware('auth:customer')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');

        Route::get('debt', [DebtController::class, 'show'])->name('debt.show');

        Route::middleware('idempotency')->group(function (): void {
            Route::post('trip-stops/{stop}/confirm', [DeliveryConfirmationController::class, 'confirm'])
                ->name('trip-stops.confirm');
            Route::post('trip-stops/{stop}/dispute', [DeliveryConfirmationController::class, 'dispute'])
                ->name('trip-stops.dispute');
        });
    });
});
