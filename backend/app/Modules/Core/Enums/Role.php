<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Rollar — PROJECT.md §4, ENUMS.md §10.
 *
 * `customer` — **alohida guard**, `users` jadvalida emas. Qolgan 8 rol
 * `spatie/laravel-permission` orqali `web` guard'da.
 */
enum Role: string
{
    case Director = 'director';
    case BranchManager = 'branch_manager';
    case Seller = 'seller';
    case Doctor = 'doctor';
    case Master = 'master';
    case Driver = 'driver';
    case Warehouse = 'warehouse';
    case Accountant = 'accountant';
    case Customer = 'customer';

    /**
     * Barcha filiallarni ko'radigan rollar (§4).
     *
     * @return array<int, self>
     */
    public static function seeingAllBranches(): array
    {
        return [self::Director, self::Accountant];
    }

    /**
     * Xodim rollari — `web` guard'da yaratiladi (`customer` bundan tashqari).
     *
     * @return array<int, self>
     */
    public static function staff(): array
    {
        $staff = [];

        foreach (self::cases() as $role) {
            if ($role !== self::Customer) {
                $staff[] = $role;
            }
        }

        return $staff;
    }
}
