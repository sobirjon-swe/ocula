<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\BrandController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\PriceController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductVariantController;
use App\Modules\Catalog\Http\Controllers\PublicServiceController;
use App\Modules\Catalog\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog — /api/v1
|--------------------------------------------------------------------------
|
| Ruxsat tekshiruvi Policy orqali (`$this->authorize()`), shuning uchun
| route'da `permission:` middleware takrorlanmaydi.
|
*/

// Landing sayt uchun ochiq (hisobsiz) — BOSQICH-11.md.
Route::middleware('throttle:30,1')->prefix('public')->name('api.public.')->group(function (): void {
    Route::get('services', [PublicServiceController::class, 'index'])->name('services');
});

Route::middleware('auth:sanctum')->name('api.')->group(function (): void {
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('services', ServiceController::class);

    // `pending` — `{product}` dan oldin, aks holda "pending" id deb o'qiladi.
    Route::get('products/pending', [ProductController::class, 'pending'])->name('products.pending');
    Route::post('products/quick', [ProductController::class, 'quickStore'])->name('products.quick');
    Route::apiResource('products', ProductController::class);

    Route::post('products/{product}/approve', [ProductController::class, 'approve'])
        ->name('products.approve');
    Route::post('products/{product}/merge', [ProductController::class, 'merge'])
        ->name('products.merge');

    Route::apiResource('products.variants', ProductVariantController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->parameters(['variants' => 'variant']);

    // Shtrix-kod skaneri uchun — `{variant}` dan oldin, aks holda
    // "by-barcode" id deb o'qiladi.
    Route::get('variants/by-barcode/{barcode}', [ProductVariantController::class, 'byBarcode'])
        ->name('variants.by-barcode');

    Route::get('variants/{variant}/prices', [PriceController::class, 'index'])->name('prices.index');
    Route::get('variants/{variant}/prices/current', [PriceController::class, 'current'])
        ->name('prices.current');
    Route::post('variants/{variant}/prices', [PriceController::class, 'store'])->name('prices.store');
});
