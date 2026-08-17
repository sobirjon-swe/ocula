<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;

/**
 * Yetkazib beruvchi tranzaksiyasi — PERMISSIONS.md §8, PROJECT.md 7.15.
 *
 * Filialga bog'lanmagan (`branch_id` yo'q) — shuning uchun `ResourcePolicy`
 * dan emas, to'g'ridan-to'g'ri ruxsatdan foydalanadi.
 */
final class SupplierTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.supplier.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.supplier_payment.create');
    }
}
