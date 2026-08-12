<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Sales — chek, buyurtma, to'lov, qaytarish, chegirma, mijozlar.
 *
 * PROJECT.md §6.4, 7.3 (ikkita alohida holat o'qi), 7.6 (qarz),
 * 7.8 (daromad tan olinishi), 7.13 (sotuv paytida katalogga qo'shish).
 */
final class SalesServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Sales';
    }
}
