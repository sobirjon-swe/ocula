<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

/**
 * Harakatlar daftari — PERMISSIONS.md §3, PROJECT.md 7.21.
 *
 * Yaratish, tahrirlash va o'chirish API orqali **umuman berilmaydi**:
 * ombor faqat hujjat orqali (kirim, sotuv, transfer) o'zgaradi. Yagona
 * to'g'ridan-to'g'ri amal — storno, u ham faqat direktorda.
 */
final class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.movement.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        if (! $user->can('warehouse.movement.view_any')) {
            return false;
        }

        if ($user->can('warehouse.stock.view_all_branches')) {
            return true;
        }

        return $model instanceof StockMovement
            && in_array($model->branch_id, $user->accessibleBranchIds(), true);
    }

    public function reverse(User $user, Model $model): bool
    {
        return $user->can('warehouse.movement.reverse') && $this->view($user, $model);
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
