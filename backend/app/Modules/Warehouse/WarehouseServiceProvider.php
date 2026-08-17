<?php

declare(strict_types=1);

namespace App\Modules\Warehouse;

use App\Modules\Warehouse\Console\RebuildStockBalances;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\LostSale;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\StockBalance;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StockRequest;
use App\Modules\Warehouse\Models\Supplier;
use App\Modules\Warehouse\Models\Transfer;
use App\Modules\Warehouse\Policies\DefectPolicy;
use App\Modules\Warehouse\Policies\LostSalePolicy;
use App\Modules\Warehouse\Policies\PurchasePolicy;
use App\Modules\Warehouse\Policies\StockBalancePolicy;
use App\Modules\Warehouse\Policies\StockMovementPolicy;
use App\Modules\Warehouse\Policies\StockRequestPolicy;
use App\Modules\Warehouse\Policies\SupplierPolicy;
use App\Modules\Warehouse\Policies\TransferPolicy;
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

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Supplier::class => SupplierPolicy::class,
            Purchase::class => PurchasePolicy::class,
            StockMovement::class => StockMovementPolicy::class,
            StockBalance::class => StockBalancePolicy::class,
            Transfer::class => TransferPolicy::class,
            StockRequest::class => StockRequestPolicy::class,
            Defect::class => DefectPolicy::class,
            LostSale::class => LostSalePolicy::class,
        ];
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([RebuildStockBalances::class]);
        }
    }
}
