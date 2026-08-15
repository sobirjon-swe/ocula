<?php

declare(strict_types=1);

use App\Modules\Clinic\Http\Controllers\PrescriptionController;
use App\Modules\Clinic\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinic — /api/v1
|--------------------------------------------------------------------------
|
| Ikkita retsept ro'yxati ataylab boshqa-boshqa yo'lda (7.11):
| `/prescriptions` — faol tiketlar, faqat o'z filialida;
| `/customers/{customer}/prescriptions` — tarix, barcha filiallar.
|
| Navbat va retsept yozish `idempotency` ostida: planshetda internet
| uzilib qayta yuborilganda ikkinchi navbat yoki ikkinchi retsept
| paydo bo'lmasin (§9).
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::get('visits/queue', [VisitController::class, 'queue'])->name('visits.queue');
    Route::get('visits', [VisitController::class, 'index'])->name('visits.index');
    Route::get('visits/{visit}', [VisitController::class, 'show'])->name('visits.show');

    Route::get('prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show'])
        ->name('prescriptions.show');
    Route::get('customers/{customer}/prescriptions', [PrescriptionController::class, 'history'])
        ->name('customers.prescriptions');

    Route::put('prescriptions/{prescription}', [PrescriptionController::class, 'update'])
        ->name('prescriptions.update');

    Route::middleware('idempotency')->group(function (): void {
        Route::post('visits', [VisitController::class, 'store'])->name('visits.store');
        Route::post('visits/{visit}/start', [VisitController::class, 'start'])->name('visits.start');
        Route::post('visits/{visit}/finish', [VisitController::class, 'finish'])->name('visits.finish');
        Route::post('visits/{visit}/cancel', [VisitController::class, 'cancel'])->name('visits.cancel');
        Route::post('visits/{visit}/no-show', [VisitController::class, 'noShow'])->name('visits.no-show');

        Route::post('prescriptions', [PrescriptionController::class, 'store'])
            ->name('prescriptions.store');
        Route::post('prescriptions/{prescription}/transfer-ticket', [PrescriptionController::class, 'transferTicket'])
            ->name('prescriptions.transfer-ticket');
    });
});
