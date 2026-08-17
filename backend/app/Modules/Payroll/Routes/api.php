<?php

declare(strict_types=1);

use App\Modules\Payroll\Http\Controllers\BonusEntryController;
use App\Modules\Payroll\Http\Controllers\BonusRuleController;
use App\Modules\Payroll\Http\Controllers\BranchPlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payroll — /api/v1
|--------------------------------------------------------------------------
|
| To'lash kassaga tegadi — `idempotency` middleware ostida (§9).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('bonus-rules', [BonusRuleController::class, 'index'])->name('bonus_rules.index');
    Route::get('bonus-entries', [BonusEntryController::class, 'index'])->name('bonus_entries.index');
    Route::get('branch-plans', [BranchPlanController::class, 'index'])->name('branch_plans.index');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('bonus-rules', [BonusRuleController::class, 'store'])->name('bonus_rules.store');
        Route::post('bonus-entries/{entry}/approve', [BonusEntryController::class, 'approve'])
            ->name('bonus_entries.approve');
        Route::post('bonus-entries/{entry}/pay', [BonusEntryController::class, 'pay'])
            ->name('bonus_entries.pay');
        Route::post('branch-plans', [BranchPlanController::class, 'store'])->name('branch_plans.store');
    });
});
