<?php

declare(strict_types=1);

namespace App\Modules\Workshop;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Workshop — ustaxona buyurtmalari, usta zaxirasi, brak, qayta ishlash.
 *
 * PROJECT.md §6.6, 7.5 (brak sabablari va kim to'lashi).
 */
final class WorkshopServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Workshop';
    }
}
