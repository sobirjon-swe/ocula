<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Support;

use App\Modules\Core\Models\User;

/**
 * Hisobotlarni filialga cheklash — PROJECT.md §4, BOSQICH-10.md §10c.
 *
 * `null` — cheklovsiz (butun tarmoq, faqat `canAccessAllBranches()`
 * bo'lganda). Bo'sh massiv — so'ralgan filial xodimga tegishli emas,
 * natija bo'sh bo'lishi kerak (begona filialni ko'rsatib qo'ymaslik
 * uchun jim qolish emas, aniq bo'sh natija).
 */
trait ScopesReportsToBranch
{
    /**
     * @return array<int, int>|null
     */
    private function resolveBranchIds(User $user, ?int $requestedBranchId): ?array
    {
        if ($user->canAccessAllBranches()) {
            return $requestedBranchId !== null ? [$requestedBranchId] : null;
        }

        $accessible = $user->accessibleBranchIds();

        if ($requestedBranchId !== null) {
            return in_array($requestedBranchId, $accessible, true) ? [$requestedBranchId] : [];
        }

        return $accessible;
    }
}
