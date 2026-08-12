<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Core — filiallar, xodimlar, rollar, smenalar, qurilmalar, sozlamalar.
 *
 * PROJECT.md §6.1. Boshqa barcha modullar shunga tayanadi:
 * `Branch`, `Location`, `Shift`, `Device` shu yerda yashaydi.
 */
final class CoreServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Core';
    }
}
