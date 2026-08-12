<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Catalog — tovarlar, variantlar, linzalar, brendlar, narxlar, xizmatlar.
 *
 * PROJECT.md §6.2, 7.2 (linza variantlari), 7.13 (katalog `yurib`
 * to'ldiriladi), 7.17 (direktor tasdig'i savdoni to'smaydi).
 */
final class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Catalog';
    }
}
