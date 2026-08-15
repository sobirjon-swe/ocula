<?php

declare(strict_types=1);

namespace App\Modules\Finance;

use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Policies\CashMovementPolicy;
use App\Modules\Finance\Services\CashRegister;
use App\Support\Contracts\CashLedger;
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

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            CashMovement::class => CashMovementPolicy::class,
        ];
    }

    /**
     * Core moduli (smena) kassaga **interfeys orqali** tegadi — shu
     * binding uni Finance klassiga bog'lanishdan saqlaydi.
     */
    public function register(): void
    {
        $this->app->singleton(CashLedger::class, CashRegister::class);
    }
}
