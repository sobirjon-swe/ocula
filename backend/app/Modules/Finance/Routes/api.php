<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\CashController;
use App\Modules\Finance\Http\Controllers\DebtController;
use App\Modules\Finance\Http\Controllers\ExpenseCategoryController;
use App\Modules\Finance\Http\Controllers\ExpenseController;
use App\Modules\Finance\Http\Controllers\SupplierTransactionController;
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

    Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense_categories.index');
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense_categories.store');

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');

    Route::get('debts', [DebtController::class, 'index'])->name('debts.index');
    Route::get('debts/{debt}', [DebtController::class, 'show'])->name('debts.show');
    Route::post('debts/{debt}/write-off', [DebtController::class, 'writeOff'])->name('debts.write_off');
    Route::post('debts/{debt}/remind', [DebtController::class, 'remind'])->name('debts.remind');

    Route::get('suppliers/{supplier}/balance', [SupplierTransactionController::class, 'balance'])
        ->name('suppliers.balance');
    Route::get('supplier-transactions', [SupplierTransactionController::class, 'index'])
        ->name('supplier_transactions.index');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('supplier-transactions', [SupplierTransactionController::class, 'store'])
            ->name('supplier_transactions.store');
    });
});
