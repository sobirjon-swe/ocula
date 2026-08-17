<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Xarajat kategoriyasi — PERMISSIONS.md §8.
 *
 * O'qish xarajat yoza oladigan har kimga ochiq (kategoriya tanlanadi),
 * o'zgartirish esa alohida `expense_category.manage` ruxsati bilan.
 */
final class ExpenseCategoryPolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'finance.expense_category.manage';
    }

    protected function readPermission(): string
    {
        return 'finance.expense.create';
    }
}
