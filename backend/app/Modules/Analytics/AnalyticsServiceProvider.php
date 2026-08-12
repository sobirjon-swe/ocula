<?php

declare(strict_types=1);

namespace App\Modules\Analytics;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Analytics — ABC tahlil, o'lik zaxira, yo'qotilgan savdo, reyting.
 *
 * PROJECT.md §6.11, 7.9 (yo'qotilgan savdo — juda qimmatli),
 * 7.18 (filiallar reytingi reja % bo'yicha, mutlaq summa bo'yicha emas).
 */
final class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Analytics';
    }
}
