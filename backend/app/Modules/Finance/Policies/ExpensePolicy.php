<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Xarajat — PERMISSIONS.md §8, PROJECT.md §6.8.
 *
 * Insert-only (7.21): `update`/`delete` yo'q, faqat `approve`.
 */
final class ExpensePolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'finance.expense';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('finance.expense.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.expense.create');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('finance.expense.approve') && $this->withinBranch($user, $model);
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
