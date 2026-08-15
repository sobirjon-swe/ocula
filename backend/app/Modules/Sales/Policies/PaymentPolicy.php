<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * To'lov — PERMISSIONS.md §4.
 *
 * Storno ataylab faqat direktorda (`sales.payment.reverse`): kassir o'z
 * xatosini o'zi yashira olmasligi kerak (ikki qo'l nazorati).
 *
 * To'lov insert-only (7.21) — tahrirlash ham, o'chirish ham yo'q.
 */
final class PaymentPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'sales.payment';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('sales.order.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('sales.order.view_any') && $this->withinBranch($user, $model);
    }

    public function reverse(User $user, Model $model): bool
    {
        return $user->can('sales.payment.reverse') && $this->withinBranch($user, $model);
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
