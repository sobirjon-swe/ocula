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
            $category['name_translations'] = [
                'uz-latn' => $category['name'],
                'ru' => match ($category['code']) {
                    'transport' => 'Транспорт', 'rent' => 'Аренда', 'utilities' => 'Коммунальные услуги', 'salary' => 'Зарплата', 'marketing' => 'Маркетинг', 'equipment' => 'Оборудование', 'supplies' => 'Хозяйственные товары', default => 'Другое'
                },
                'en' => match ($category['code']) {
                    'transport' => 'Transport', 'rent' => 'Rent', 'utilities' => 'Utilities', 'salary' => 'Salary', 'marketing' => 'Marketing', 'equipment' => 'Equipment', 'supplies' => 'Supplies', default => 'Other'
                },
            ];
            ExpenseCategory::query()->firstOrCreate(['code' => $category['code']], $category);
        }
    }
}
