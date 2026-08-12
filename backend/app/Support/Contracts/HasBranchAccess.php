<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Filialga kirish huquqi — PROJECT.md §4, ANALIZ 3.11.
 *
 * `BranchScope` shu interfeys orqali so'raydi, `User` modelini
 * to'g'ridan-to'g'ri bilmaydi (Support qatlami modullardan mustaqil).
 */
interface HasBranchAccess
{
    /**
     * `director` va `accountant` — barcha filiallarni ko'radi (§4).
     */
    public function canAccessAllBranches(): bool;

    /**
     * Asosiy filial (`users.branch_id`) + `branch_user` pivotdagilar.
     *
     * @return array<int, int>
     */
    public function accessibleBranchIds(): array;
}
