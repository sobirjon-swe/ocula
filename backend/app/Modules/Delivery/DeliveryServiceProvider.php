<?php

declare(strict_types=1);

namespace App\Modules\Delivery;

use App\Modules\Delivery\Console\ExpireDeliveryConfirmations;
use App\Modules\Delivery\Models\CashCollection;
use App\Modules\Delivery\Models\DriverBalance;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Delivery\Policies\CashCollectionPolicy;
use App\Modules\Delivery\Policies\DriverBalancePolicy;
use App\Modules\Delivery\Policies\TripPolicy;
use App\Modules\Delivery\Policies\TripStopPolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Delivery — yo'l varaqasi, haydovchi balansi, yetkazish, inkassatsiya.
 *
 * PROJECT.md §6.7, 7.4 (asimmetrik yetkazish tasdig'i),
 * 7.10 (transfer usullari: own_driver / taxi / by_hand). BOSQICH-8.md.
 */
final class DeliveryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Delivery';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Trip::class => TripPolicy::class,
            TripStop::class => TripStopPolicy::class,
            DriverBalance::class => DriverBalancePolicy::class,
            CashCollection::class => CashCollectionPolicy::class,
        ];
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireDeliveryConfirmations::class]);
        }
    }
}
