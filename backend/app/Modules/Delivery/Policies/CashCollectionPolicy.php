<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;

/**
 * Inkassatsiya — PERMISSIONS.md §7, BOSQICH-8.md §5 #9.
 *
 * Yozuvni faqat **qabul qiluvchi** taraf yaratadi (`delivery.collection.receive`)
 * — kassir/buxgalter/direktor/filial boshlig'i. `delivery.collection.create`
 * (haydovchida) hozircha ishlatilmagan ilgak: sxemada `collections` uchun
 * "so'rov/tasdiq" oralig'i yo'q (bitta insert-only qator), shuning uchun
 * ikki tomonlama nazorat (nuance #3) yozuvni faqat qabul qiluvchi
 * yaratishi bilan ta'minlanadi. Bu — `finance.debt.remind` kabi
 * Bosqich 3'dan qolgan, endi to'liq ishlatilmagan ilgaklardan biri.
 */
final class CashCollectionPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'delivery.collection';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('delivery.collection.receive');
    }

    public function create(User $user): bool
    {
        return $user->can('delivery.collection.receive');
    }
}
