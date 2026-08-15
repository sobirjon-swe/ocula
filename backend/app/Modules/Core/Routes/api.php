<?php

declare(strict_types=1);

use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\BranchController;
use App\Modules\Core\Http\Controllers\DeviceController;
use App\Modules\Core\Http\Controllers\SettingController;
use App\Modules\Core\Http\Controllers\ShiftController;
use App\Modules\Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core — /api/v1
|--------------------------------------------------------------------------
|
| Prefiks va `api` middleware guruhi `ModuleServiceProvider` da ulanadi.
| Ruxsat tekshiruvi Policy orqali (kontrollerda `$this->authorize()`),
| shuning uchun route'da `permission:` middleware takrorlanmaydi.
|
*/

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('pin', [AuthController::class, 'pin'])->name('pin');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::apiResource('branches', BranchController::class);

    Route::apiResource('users', UserController::class);
    Route::put('users/{user}/roles', [UserController::class, 'assignRoles'])->name('users.roles');
    Route::put('users/{user}/debt-limit', [UserController::class, 'setDebtLimit'])->name('users.debt-limit');
    Route::put('users/{user}/pin', [UserController::class, 'setPin'])->name('users.pin');

    // Qurilma o'chirilmaydi — tokeni bekor qilinadi (7.14).
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::post('devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('shifts/current', [ShiftController::class, 'current'])->name('shifts.current');
    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');

    // Smena ochish/yopish — takroriy yuborilsa dublikat bo'lmasin (§9).
    Route::middleware('idempotency')->group(function (): void {
        Route::post('shifts', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });
});
