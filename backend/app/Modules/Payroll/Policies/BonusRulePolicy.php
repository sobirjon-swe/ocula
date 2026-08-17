<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Mukofot qoidasi — PERMISSIONS.md §9, PROJECT.md 7.19.
 *
 * Foiz biriktirish faqat direktorda (`bonus_rule.manage`); qoidalarni
 * ko'rish esa buxgalter/filial menejerida ham bor (`bonus_rule.view`).
 */
final class BonusRulePolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'payroll.bonus_rule.manage';
    }

    protected function readPermission(): string
    {
        return 'payroll.bonus_rule.view';
    }
}
