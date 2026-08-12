<?php

declare(strict_types=1);

namespace App\Modules\Delivery;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Delivery — yo'l varaqasi, haydovchi balansi, yetkazish, inkassatsiya.
 *
 * PROJECT.md §6.7, 7.4 (asimmetrik yetkazish tasdig'i),
 * 7.10 (transfer usullari: own_driver / taxi / by_hand).
 */
final class DeliveryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Delivery';
    }
}
