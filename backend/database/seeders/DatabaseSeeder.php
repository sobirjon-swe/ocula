<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Bosqich 1 seeder'lari — ANALIZ.md §6.
 *
 * Tartib muhim: rollar va filiallar demo xodimlardan oldin kerak.
 * Demo xodimlar **faqat dev/test** muhitida yaratiladi.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CustomerPermissionSeeder::class,
            BranchSeeder::class,
            ServiceSeeder::class,
            SettingSeeder::class,
            ExpenseCategorySeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
