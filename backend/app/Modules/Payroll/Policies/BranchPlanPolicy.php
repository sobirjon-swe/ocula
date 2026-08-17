<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Filial oylik rejasi — PERMISSIONS.md §9, PROJECT.md 7.18.
 */
final class BranchPlanPolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'payroll.plan.manage';
    }

    protected function readPermission(): string
    {
        return 'payroll.plan.view';
    }
}
