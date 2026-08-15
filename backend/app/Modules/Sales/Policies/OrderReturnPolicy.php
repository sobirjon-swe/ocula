<?php

declare(strict_types=1);

namespace App\Modules\Sales\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Qaytarish hujjati — PERMISSIONS.md §4.
 *
 * `sales.return.approve` alohida turadi: do'kon xohlasa, qaytarishni
 * sotuvchi rasmiylashtirib, filial boshlig'i tasdiqlaydigan qilib
 * sozlashi mumkin.
 *
 * Hujjat tahrirlanmaydi va o'chirilmaydi: u ombor va kassa
 * harakatlarini tug'dirgan, ularni orqaga qaytarish faqat storno
 * orqali bo'ladi (7.21).
 */
final class OrderReturnPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'sales.return';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('sales.order.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('sales.order.view_any') && $this->withinBranch($user, $model);
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('sales.return.approve') && $this->withinBranch($user, $model);
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
