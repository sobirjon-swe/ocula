<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
|
| Prefiks `bootstrap/app.php` da (`apiPrefix: 'api/v1'`) belgilangan.
| Bu faylda faqat **global** endpointlar turadi. Modulga tegishli
| route'lar har modulning `Routes/api.php` faylida yashaydi va
| o'sha modulning ServiceProvider'i orqali ro'yxatga olinadi
| (App\Support\Providers\ModuleServiceProvider).
|
*/

Route::get('ping', fn (): JsonResponse => response()->json([
    'data' => [
        'service' => config('app.name'),
        'version' => config('optika.version'),
        'time' => now()->toIso8601String(),
    ],
]))->name('api.ping');
