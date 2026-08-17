<?php

declare(strict_types=1);

namespace App\Modules\Payroll;

use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Payroll\Models\BranchPlan;
use App\Modules\Payroll\Policies\BonusEntryPolicy;
use App\Modules\Payroll\Policies\BonusRulePolicy;
use App\Modules\Payroll\Policies\BranchPlanPolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Payroll — mukofot qoidalari, mukofot yozuvlari, filial rejalari.
 *
 * PROJECT.md 7.12 (mukofot hisoblash), 7.18 (filial rejalari),
 * 7.19 (shaxsiy foiz).
 */
final class PayrollServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Payroll';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            BonusRule::class => BonusRulePolicy::class,
            BonusEntry::class => BonusEntryPolicy::class,
            BranchPlan::class => BranchPlanPolicy::class,
        ];
    }
}
