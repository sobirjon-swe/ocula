<?php

declare(strict_types=1);

namespace App\Modules\Customer;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Customer — mijoz kabineti API (Telegram Mini App + PWA). BOSQICH-9.md.
 *
 * PROJECT.md §6.9, §11 (Bosqich 9). Mijoz **alohida guard** (customer),
 * xodimlar bilan bir jadvalda emas (§4) — model o'zi Sales modulida
 * (`App\Modules\Sales\Models\Customer`), bu yer faqat kabinet API'si.
 */
final class CustomerServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Customer';
    }
}
