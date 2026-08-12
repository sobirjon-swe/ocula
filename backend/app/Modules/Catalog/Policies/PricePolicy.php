<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Narx — PERMISSIONS.md §2.
 *
 * Narx yozuvi **o'zgartirilmaydi va o'chirilmaydi**: yangi narx qo'yilganda
 * avvalgisining `valid_to` yopiladi (SCHEMA.md §2). Shu tufayli o'tgan oy
 * hisoboti keyingi narx o'zgarishidan buzilmaydi.
 */
final class PricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.price.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('catalog.price.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.price.update');
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
