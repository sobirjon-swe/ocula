<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Kassa daftari — PERMISSIONS.md §8.
 *
 * Ko'rish (`finance.cash.view`) sotuvchida ham bor — u o'z smenasidagi
 * pulni ko'rishi kerak. Yozish va storno esa alohida ruxsatlar:
 * kassaga qo'lda yozuv qo'yish buxgalter/direktor ishi.
 *
 * Yozuv **hech qachon** tahrirlanmaydi va o'chirilmaydi (insert-only,
 * 7.21) — shuning uchun `update` va `delete` doim `false`.
 */
final class CashMovementPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'finance.cash';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('finance.cash.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('finance.cash.view') && $this->withinBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('finance.cash_movement.create');
    }

    public function reverse(User $user, Model $model): bool
    {
        return $user->can('finance.cash_movement.reverse') && $this->withinBranch($user, $model);
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
