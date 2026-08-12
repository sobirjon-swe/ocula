<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Enums\Locale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo xodimlar — faqat dev va test uchun.
 *
 * Login **telefon bo'yicha**, parol hammada `password`.
 * Prodda ishga tushmaydi — `DatabaseSeeder` tekshiradi.
 *
 * Bosqich 1 da `director` va `seller` rollari to'liq ishlanadi
 * (ANALIZ.md §4 dagi oxirgi risk), qolganlari keyingi bosqichlarda.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::query()->where('code', 'A')->firstOrFail();

        // Markaziy filialda o'tiradigan xodimlar (PROJECT.md §1.1).
        $this->makeUser('Direktor', '+998901000001', Role::Director, $main, debtLimit: '100000000.00');
        $this->makeUser('Buxgalter', '+998901000002', Role::Accountant, $main);
        $this->makeUser('Shifokor', '+998901000003', Role::Doctor, $main);
        $this->makeUser('Usta', '+998901000004', Role::Master, $main);
        $this->makeUser('Haydovchi', '+998901000005', Role::Driver, $main);
        $this->makeUser('Omborchi', '+998901000006', Role::Warehouse, $main);

        // Har filialga bittadan mudir va sotuvchi.
        $index = 10;

        foreach (Branch::query()->orderBy('code')->get() as $branch) {
            $this->makeUser(
                "{$branch->code} filial mudiri",
                '+99890100'.str_pad((string) $index++, 4, '0', STR_PAD_LEFT),
                Role::BranchManager,
                $branch,
            );

            $this->makeUser(
                "{$branch->code} filial sotuvchisi",
                '+99890100'.str_pad((string) $index++, 4, '0', STR_PAD_LEFT),
                Role::Seller,
                $branch,
                // Sotuvchi shu summagacha qarzga bera oladi, undan yuqorisi
                // direktor tasdig'i bilan (7.6, §15 #3).
                debtLimit: '500000.00',
            );
        }
    }

    private function makeUser(
        string $name,
        string $phone,
        Role $role,
        Branch $branch,
        string $debtLimit = '0.00',
    ): void {
        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'branch_id' => $branch->id,
                'debt_limit' => $debtLimit,
                'locale' => Locale::UzLatn,
                'is_active' => true,
            ],
        );

        $user->syncRoles([$role->value]);
    }
}
