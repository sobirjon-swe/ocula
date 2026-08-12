<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Filial — PERMISSIONS.md §1.
 *
 * Matritsa bo'yicha `core.branch.*` to'liq faqat `director` da;
 * `branch_manager` va `accountant` da faqat `view`.
 */
final class BranchPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'core.branch';
    }

    /**
     * Filialning o'zida `branch_id` yo'q — cheklov birlamchi kalit
     * bo'yicha ishlaydi.
     */
    protected function withinBranch(User $user, Model $model): bool
    {
        if (! $model instanceof Branch || $user->canAccessAllBranches()) {
            return true;
        }

        return in_array($model->id, $user->accessibleBranchIds(), true);
    }
}
