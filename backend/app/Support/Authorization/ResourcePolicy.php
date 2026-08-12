<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy'lar uchun umumiy asos — PERMISSIONS.md "Konventsiya".
 *
 * Ruxsat nomi qat'iy `module.resource.action` shaklida hosil qilinadi,
 * shuning uchun Policy'da `if ($user->isDirector())` kabi qattiq shart
 * **yozilmaydi** (PERMISSIONS.md, qoida 2).
 *
 * Filial cheklovi ruxsatdan **alohida** ishlaydi (qoida 4): ro'yxatni
 * `BranchScope` qisqartiradi, bitta yozuvni ochishni esa shu yerdagi
 * `withinBranch()` to'sadi — aks holda `/branches/7` ni qo'lda yozib
 * begona filial yozuvini ochib bo'lardi.
 */
abstract class ResourcePolicy
{
    /**
     * Ruxsat prefiksi — `core.branch`, `catalog.product`, …
     */
    abstract protected function permission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission().'.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission().'.view') && $this->withinBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission().'.update') && $this->withinBranch($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission().'.delete') && $this->withinBranch($user, $model);
    }

    /**
     * Yozuv foydalanuvchi ko'ra oladigan filialga tegishlimi.
     *
     * Filialga bog'lanmagan yozuvlar (katalog, sozlamalar) uchun cheklov
     * yo'q — ular butun tarmoq uchun umumiy.
     */
    protected function withinBranch(User $user, Model $model): bool
    {
        $branchId = $model->getAttribute('branch_id');

        if ($branchId === null || $user->canAccessAllBranches()) {
            return true;
        }

        return in_array((int) $branchId, $user->accessibleBranchIds(), true);
    }
}
