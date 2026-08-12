<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Customer — mijoz kabineti API (Telegram Mini App + PWA).
 *
 * PROJECT.md §6.9. Mijoz **alohida guard** (customer), xodimlar bilan
 * bir jadvalda emas (§4).
 */
final class CustomerServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Customer';
    }
}
