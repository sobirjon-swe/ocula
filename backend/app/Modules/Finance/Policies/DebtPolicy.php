<?php

declare(strict_types=1);

namespace App\Modules\Finance\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Qarz registri — PERMISSIONS.md §8, PROJECT.md 7.6.
 *
 * `create`/`update`/`delete` yo'q — qarz faqat `DebtRegistry` orqali
 * avtomatik tug'iladi (BOSQICH-10.md §10a). `write_off` PERMISSIONS.md
 * bo'yicha faqat direktorda.
 */
final class DebtPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'finance.debt';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('finance.debt.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('finance.debt.view_any') && $this->withinBranch($user, $model);
    }

    public function writeOff(User $user, Model $model): bool
    {
        return $user->can('finance.debt.write_off') && $this->withinBranch($user, $model);
    }

    public function remind(User $user, Model $model): bool
    {
        return $user->can('finance.debt.remind') && $this->withinBranch($user, $model);
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
