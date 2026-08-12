<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Tovar — PERMISSIONS.md §2, PROJECT.md 7.13, 7.17.
 *
 * Katalog **filialga bog'lanmagan** — tovar butun tarmoq uchun bitta,
 * shuning uchun `withinBranch()` cheklovi bu yerda ishlamaydi.
 */
final class ProductPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'catalog.product';
    }

    /**
     * Sotuv paytida tez qo'shish (7.13) — natija `status = pending`.
     *
     * Sotuvchida `create` yo'q, lekin `quick_create` bor: savdo
     * to'xtamasligi kerak.
     */
    public function quickCreate(User $user): bool
    {
        return $user->can('catalog.product.quick_create');
    }

    /**
     * Tasdiqlash/rad etish (7.17) — faqat direktorda.
     */
    public function approve(User $user, Model $model): bool
    {
        return $user->can('catalog.product.approve');
    }

    /**
     * Dublikatlarni birlashtirish (7.13).
     */
    public function merge(User $user, Model $model): bool
    {
        return $user->can('catalog.product.merge');
    }

    /**
     * O'chirish faqat **harakati yo'q** tovarga (PERMISSIONS.md §2).
     * Harakat tekshiruvi kontrollerda: bu yerda faqat ruxsat.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->can('catalog.product.delete');
    }

    /**
     * Katalog umumiy — filial cheklovi qo'llanmaydi.
     */
    protected function withinBranch(User $user, Model $model): bool
    {
        return true;
    }
}
