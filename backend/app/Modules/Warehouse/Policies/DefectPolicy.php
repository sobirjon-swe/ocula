<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Brak — PERMISSIONS.md §3, PROJECT.md 7.5.
 *
 * Xabar berish keng (usta, sotuvchi, omborchi — brakni topgan odam
 * darrov yozishi kerak), ko'rish va tasdiqlash esa torroq: zarar
 * statistikasi va kim to'lashi masalasi rahbariyatniki.
 *
 * Yozuv **o'chirilmaydi va tahrirlanmaydi**: u ombor harakatini
 * tug'dirgan bo'lishi mumkin.
 */
final class DefectPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'warehouse.defect';
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('warehouse.defect.view_any') && $this->withinBranch($user, $model);
    }

    /**
     * Brak haqida xabar berish — `report`, `create` emas
     * (PERMISSIONS.md §3 dagi nom).
     */
    public function create(User $user): bool
    {
        return $user->can('warehouse.defect.report');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('warehouse.defect.approve') && $this->withinBranch($user, $model);
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
