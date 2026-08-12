<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Enums\BranchType;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use Illuminate\Database\Seeder;

/**
 * 5 filial — PROJECT.md §1.1.
 *
 * A — asosiy: savdo + markaziy ombor + ustaxona + shifokor + direktor.
 * B, C, D, E — faqat savdo nuqtalari.
 *
 * Har filialga bitta `warehouse` location ochiladi — 1-versiyada sotuv
 * ham shundan chiqadi, `floor` ishlatilmaydi (§15 #22).
 */
class BranchSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, type: BranchType, open: string}>
     */
    private const array BRANCHES = [
        ['code' => 'A', 'name' => 'A filial (markaziy)', 'type' => BranchType::Main, 'open' => '09:00'],
        ['code' => 'B', 'name' => 'B filial', 'type' => BranchType::Shop, 'open' => '10:00'],
        ['code' => 'C', 'name' => 'C filial', 'type' => BranchType::Shop, 'open' => '10:00'],
        ['code' => 'D', 'name' => 'D filial', 'type' => BranchType::Shop, 'open' => '10:00'],
        ['code' => 'E', 'name' => 'E filial', 'type' => BranchType::Shop, 'open' => '10:00'],
    ];

    public function run(): void
    {
        foreach (self::BRANCHES as $data) {
            $branch = Branch::query()->firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'open_time' => $data['open'],
                    'close_time' => '22:00',
                    'is_active' => true,
                ],
            );

            Location::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'type' => LocationType::Warehouse],
                ['name' => 'Ombor', 'is_active' => true],
            );
        }
    }
}
