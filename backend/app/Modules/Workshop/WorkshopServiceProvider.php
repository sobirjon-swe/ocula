<?php

declare(strict_types=1);

namespace App\Modules\Workshop;

use App\Modules\Workshop\Models\WorkOrder;
use App\Modules\Workshop\Policies\WorkOrderPolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Workshop — ustaxona kanbani, usta zaxirasi, brak, qayta ishlash.
 *
 * PROJECT.md §6.6, 7.5 (brak sabablari), 7.12 (mukofot va brak),
 * 7.14 (umumiy planshetda PIN bilan almashish).
 */
final class WorkshopServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Workshop';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            WorkOrder::class => WorkOrderPolicy::class,
        ];
    }
}
