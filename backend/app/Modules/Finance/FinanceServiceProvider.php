<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Finance — kassa, xarajat, qarz, yetkazib beruvchilar, smena yopilishi.
 *
 * PROJECT.md §6.8, 7.6 (qarz muddatlari), 7.15 (yetkazib beruvchi bilan
 * ikki tomonlama balans), 7.21 (insert-only, storno).
 */
final class FinanceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Finance';
    }
}
