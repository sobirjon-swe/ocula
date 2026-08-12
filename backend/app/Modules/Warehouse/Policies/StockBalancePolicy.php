<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Models\StockBalance;
use Illuminate\Database\Eloquent\Model;

/**
 * Qoldiq — PERMISSIONS.md §3 (`warehouse.stock.view`).
 *
 * Qoldiqni ko'rish va **harakatlar daftarini** ko'rish — ikki xil
 * ruxsat. Sotuvchiga "bu tovardan nechta qoldi" kerak, "kim qachon
 * nima yozgan" esa kerak emas (va tannarx ham chiqmasligi kerak).
 *
 * Kesh yozuvlari faqat o'qish uchun: ular `stock_movements` dan
 * hosil bo'ladi, API orqali o'zgartirilmaydi.
 */
final class StockBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.stock.view');
    }

    public function view(User $user, Model $model): bool
    {
        if (! $user->can('warehouse.stock.view')) {
            return false;
        }

        if ($user->can('warehouse.stock.view_all_branches')) {
            return true;
        }

        return $model instanceof StockBalance
            && in_array($model->branch_id, $user->accessibleBranchIds(), true);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
