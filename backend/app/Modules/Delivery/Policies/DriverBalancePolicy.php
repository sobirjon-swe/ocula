<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Haydovchi balansi — PERMISSIONS.md §7, nuance #6.
 *
 * `delivery.balance.view_any` — barcha haydovchilar (director,
 * branch_manager, accountant). `delivery.balance.view` — haydovchida
 * ham bor, lekin **faqat o'ziniki**.
 */
final class DriverBalancePolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'delivery.balance';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('delivery.balance.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        if ($user->can('delivery.balance.view_any')) {
            return true;
        }

        return $user->can('delivery.balance.view')
            && (int) $model->getAttribute('driver_id') === $user->id;
    }
}
