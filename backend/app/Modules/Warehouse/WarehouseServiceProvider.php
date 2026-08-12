<?php

declare(strict_types=1);

namespace App\Modules\Warehouse;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Warehouse — kirim, transfer, inventarizatsiya, brak, qoldiq, FIFO.
 *
 * PROJECT.md §6.3, 7.1 (qoldiq = harakatlar daftari), 7.20 (FIFO
 * qatlamlari), 7.21 (insert-only, storno).
 */
final class WarehouseServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Warehouse';
    }
}
