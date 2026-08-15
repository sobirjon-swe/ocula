<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Buyurtma — PERMISSIONS.md §4.
 *
 * `sales.order.view` degan alohida ruxsat yo'q: ro'yxatni ko'ra olgan
 * kartochkani ham ko'radi. Filial cheklovi esa saqlanadi — sotuvchi
 * begona filial chekini ochib bo'lmaydi.
 *
 * Topshirish, bekor qilish va holat o'zgartirish — **uchta alohida
 * ruxsat**: tovar berish bilan hujjatni yuritish bir xil mas'uliyat
 * emas.
 *
 * Buyurtma o'chirilmaydi: bekor qilinadi yoki qaytariladi.
 */
final class OrderPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'sales.order';
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('sales.order.view_any') && $this->withinBranch($user, $model);
    }

    public function changeStatus(User $user, Model $model): bool
    {
        return $user->can('sales.order.change_status') && $this->withinBranch($user, $model);
    }

    public function deliver(User $user, Model $model): bool
    {
        return $user->can('sales.order.deliver') && $this->withinBranch($user, $model);
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can('sales.order.cancel') && $this->withinBranch($user, $model);
    }

    /**
     * Qarz eslatmasini qo'lda yuborish (BOSQICH-7.md §4.2). `finance.debt.remind`
     * director va accountant'da — ikkalasi ham barcha filialni ko'radi
     * (PERMISSIONS.md §8), shuning uchun filial cheklovi qo'shilmadi.
     */
    public function remindDebt(User $user, Model $model): bool
    {
        return $user->can('finance.debt.remind');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
