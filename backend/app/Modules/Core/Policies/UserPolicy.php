<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Xodim — PERMISSIONS.md §1.
 *
 * `assign_role`, `set_debt_limit` va `set_pin` alohida ruxsatlar:
 * rol biriktirish va qarz limiti faqat `director` da (matritsa),
 * PIN esa filial boshlig'ida ham bor (7.14).
 */
final class UserPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'core.user';
    }

    public function assignRole(User $user, Model $model): bool
    {
        return $user->can('core.user.assign_role') && $this->withinBranch($user, $model);
    }

    public function setDebtLimit(User $user, Model $model): bool
    {
        return $user->can('core.user.set_debt_limit') && $this->withinBranch($user, $model);
    }

    public function setPin(User $user, Model $model): bool
    {
        return $user->can('core.user.set_pin') && $this->withinBranch($user, $model);
    }

    /**
     * Xodimni o'chirish = faolsizlantirish (PERMISSIONS.md §1).
     * O'zini o'chirib qo'yishning oldi olinadi.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($model instanceof User && $model->id === $user->id) {
            return false;
        }

        return parent::delete($user, $model);
    }

    /**
     * Xodim `branch_id` orqali filialga bog'langan.
     */
    protected function withinBranch(User $user, Model $model): bool
    {
        if ($user->canAccessAllBranches()) {
            return true;
        }

        $branchId = $model->getAttribute('branch_id');

        if ($branchId === null) {
            // Filialsiz xodim (direktor) — faqat hammani ko'ruvchilar uchun.
            return false;
        }

        return in_array((int) $branchId, $user->accessibleBranchIds(), true);
    }
}
