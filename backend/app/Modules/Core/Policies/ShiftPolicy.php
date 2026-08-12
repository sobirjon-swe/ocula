<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Smena — PERMISSIONS.md §1.
 *
 * Smenani **yaratish/tahrirlash yo'q** — faqat ochish va yopish, chunki
 * kunlik hisobot shu jadval bo'yicha quriladi (§15 #24).
 */
final class ShiftPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'core.shift';
    }

    public function open(User $user): bool
    {
        return $user->can('core.shift.open');
    }

    /**
     * Yopish — ruxsat + smena o'z filialida bo'lishi.
     */
    public function close(User $user, Model $model): bool
    {
        return $user->can('core.shift.close') && $this->withinBranch($user, $model);
    }

    public function approveDifference(User $user, Model $model): bool
    {
        return $user->can('core.shift.approve_difference') && $this->withinBranch($user, $model);
    }

    /**
     * Smena yozuvini yaratish/o'chirish API orqali umuman berilmaydi.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * `core.shift.view_all_branches` — `BranchScope` ni chetlab o'tuvchi
     * alohida ruxsat (PERMISSIONS.md konventsiyasi).
     */
    protected function withinBranch(User $user, Model $model): bool
    {
        if ($user->can('core.shift.view_all_branches')) {
            return true;
        }

        return $model instanceof Shift && parent::withinBranch($user, $model);
    }
}
