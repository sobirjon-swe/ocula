<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Yetkazib beruvchi — PERMISSIONS.md §3.
 *
 * Alohida `supplier.manage` ruxsati ro'yxatda yo'q: yetkazib beruvchi
 * kirim hujjatining ajralmas qismi, shuning uchun uni kirim yaratuvchi
 * boshqaradi, ro'yxatni esa kirimlarni ko'ra oladigan har kim ko'radi.
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
}
