<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics — /api/v1
|--------------------------------------------------------------------------
|
| Barcha endpointlar faqat o'qish uchun — yozuv boshqa modullardan
| keladi. Har biri o'z `analytics.*.view` ruxsati bilan cheklangan.
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard'])
        ->name('analytics.dashboard');
    Route::get('analytics/cash-report', [AnalyticsController::class, 'cashReport'])
        ->name('analytics.cash_report');
    Route::get('analytics/profit-report', [AnalyticsController::class, 'profitReport'])
        ->name('analytics.profit_report');
    Route::get('analytics/abc-analysis', [AnalyticsController::class, 'abcAnalysis'])
        ->name('analytics.abc_analysis');
    Route::get('analytics/dead-stock', [AnalyticsController::class, 'deadStock'])
        ->name('analytics.dead_stock');
    Route::get('analytics/lost-sale-report', [AnalyticsController::class, 'lostSaleReport'])
        ->name('analytics.lost_sale_report');
    Route::get('analytics/staff-report', [AnalyticsController::class, 'staffReport'])
        ->name('analytics.staff_report');
    Route::get('analytics/branch-rating', [AnalyticsController::class, 'branchRating'])
        ->name('analytics.branch_rating');
});
