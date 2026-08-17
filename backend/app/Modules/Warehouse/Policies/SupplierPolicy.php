<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ReferencePolicy;

/**
 * Yetkazib beruvchi — PERMISSIONS.md §3, §8.
 *
 * Alohida `supplier.manage` ruxsati ro'yxatda yo'q: yetkazib beruvchi
 * kirim hujjatining ajralmas qismi, shuning uchun uni kirim yaratuvchi
 * boshqaradi, ro'yxatni esa kirimlarni ko'ra oladigan har kim ko'radi.
 *
 * `viewBalance` — Finance ning ikki tomonlama hisobi (7.15, BOSQICH-10.md
 * §10a), alohida ruxsat: kirim ko'ra oladigan hamma balansni ko'rmaydi.
 */
final class SupplierPolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'warehouse.purchase.create';
    }

    protected function readPermission(): string
    {
        return 'warehouse.purchase.view_any';
    }

    public function viewBalance(User $user): bool
    {
        return $user->can('finance.supplier.balance.view');
    }
}
