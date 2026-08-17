<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `customer` guard ruxsatlari — PERMISSIONS.md §12, BOSQICH-9.md §4.
 *
 * `RolePermissionSeeder` bu ruxsatlarni ataylab yaratmaydi (o'z
 * docblokida "Customer modulida keladi" deb yozilgan). Bu yerda —
 * chunki `customers` jadvali va autentifikatsiya shu bosqichda keladi.
 *
 * `web` guard'dan farqli, bu yerda **rollar matritsasi yo'q** — har bir
 * mijoz bir xil olti ruxsatga ega, shuning uchun bitta `customer` roli
 * yetarli (`AuthenticateViaTelegram` yangi mijoz yaratganda biriktiradi).
 */
class CustomerPermissionSeeder extends Seeder
{
    private const array PERMISSIONS = [
        'customer.profile.view', 'customer.profile.update',
        'customer.order.view_own', 'customer.prescription.view_own',
        'customer.debt.view_own', 'customer.delivery.confirm',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'customer');
        }

        // Yangi yaratilgan ruxsatlar keshda yo'q — `syncPermissions()`
        // ularni topa olmasligi uchun keshni shu yerda ham tozalaymiz.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate('customer', 'customer');
        $role->syncPermissions(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
