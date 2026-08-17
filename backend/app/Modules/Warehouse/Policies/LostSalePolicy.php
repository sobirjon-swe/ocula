<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Policies;

use App\Modules\Core\Models\User;

/**
 * Yo'qotilgan savdo — PERMISSIONS.md §3, PROJECT.md 7.9.
 *
 * Yozuv tahrirlanmaydi/o'chirilmaydi — faqat `view_any`/`create`.
 */
final class LostSalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.lost_sale.view_any');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouse.lost_sale.create');
    }
}
