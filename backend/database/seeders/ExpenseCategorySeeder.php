<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Boshlang'ich xarajat kategoriyalari — PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * `transport` kodi `SendTransfer` tomonidan taksi xarajatini avtomatik
 * yozishda ishlatiladi — kod shu yerda qat'iylashadi.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'transport', 'name' => 'Transport'],
            ['code' => 'rent', 'name' => 'Ijara'],
            ['code' => 'utilities', 'name' => 'Kommunal xizmatlar'],
            ['code' => 'salary', 'name' => 'Oylik'],
            ['code' => 'marketing', 'name' => 'Marketing'],
            ['code' => 'equipment', 'name' => 'Uskuna'],
            ['code' => 'supplies', 'name' => "Xo'jalik buyumlari"],
            ['code' => 'other', 'name' => 'Boshqa'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::query()->firstOrCreate(['code' => $category['code']], $category);
        }
    }
}
