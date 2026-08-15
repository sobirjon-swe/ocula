<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Ichki so'rov — PERMISSIONS.md §3.
 *
 * So'rash sotuvchida ham bor (u mijoz oldida turibdi), tasdiqlash va
 * bajarish esa filial boshlig'i/omborchida: tovar berishni ombor
 * qarori bo'lishi kerak.
 *
 * Ko'rish uchun alohida ruxsat yo'q — transfer ro'yxatini ko'ra olgan
 * so'rovlarni ham ko'radi. Filial cheklovi `TwoSidedBranchScope` da.
 */
final class StockRequestPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'warehouse.request';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.request.create')
            || $user->can('warehouse.transfer.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('warehouse.request.approve');
    }

    public function fulfill(User $user, Model $model): bool
    {
        return $user->can('warehouse.request.fulfill');
    }

    /**
     * Bekor qilishni so'ragan odamning o'zi ham qila oladi — bu o'z
     * so'rovini qaytarib olish, boshqaning qaroriga aralashish emas.
     */
    public function cancel(User $user, Model $model): bool
    {
        return $user->can('warehouse.request.create');
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
