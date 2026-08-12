<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Kirim hujjati — PERMISSIONS.md §3.
 *
 * Yaratish, qabul qilish va bekor qilish — **uchta alohida ruxsat**:
 * hujjatni kiritgan odam uni o'zi qabul qilib qo'ymasligi mumkin
 * (do'kon xohlasa ikki qo'l nazoratini yoqadi).
 */
final class PurchasePolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'warehouse.purchase';
    }

    /**
     * `purchase.view` ruxsati alohida yo'q — ro'yxatni ko'ra olgan
     * kartochkani ham ko'radi.
     */
    public function view(User $user, Model $model): bool
    {
        return $user->can('warehouse.purchase.view_any') && $this->withinBranch($user, $model);
    }

    /**
     * Faqat qoralama tahrirlanadi — qabul qilingan hujjat ombor
     * harakatlarini tug'dirgan, uni o'zgartirish tarixni buzardi.
     */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Purchase
            && $model->status === PurchaseStatus::Draft
            && $user->can('warehouse.purchase.create')
            && $this->withinBranch($user, $model);
    }

    public function receive(User $user, Model $model): bool
    {
        return $user->can('warehouse.purchase.receive') && $this->withinBranch($user, $model);
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can('warehouse.purchase.cancel') && $this->withinBranch($user, $model);
    }

    /**
     * Hujjat o'chirilmaydi — bekor qilinadi (`cancelled`).
     */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
