<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions\Price;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Narx o'rnatish — SCHEMA.md §2, ANALIZ 3.16.
 *
 * Yangi narx qo'yilganda avvalgisining `valid_to` **yopiladi**, yozuvning
 * o'zi o'chirilmaydi. Shu tufayli o'tgan oy hisoboti keyingi narx
 * o'zgarishidan buzilmaydi: har bir sotuv o'z davridagi narxni topadi.
 *
 * Global (`branch_id = null`) va filial narxlari alohida zanjir yuritadi —
 * filial narxini yopish global narxga tegmaydi.
 */
final class SetPrice
{
    public function handle(
        User $author,
        ProductVariant $variant,
        Money $price,
        ?int $branchId = null,
        ?CarbonInterface $validFrom = null,
    ): Price {
        $from = $validFrom ?? now();

        return DB::transaction(function () use ($author, $variant, $price, $branchId, $from): Price {
            Price::query()
                ->where('variant_id', $variant->id)
                ->when(
                    $branchId === null,
                    fn ($query) => $query->whereNull('branch_id'),
                    fn ($query) => $query->where('branch_id', $branchId),
                )
                ->whereNull('valid_to')
                ->lockForUpdate()
                ->update(['valid_to' => $from]);

            return Price::create([
                'variant_id' => $variant->id,
                'branch_id' => $branchId,
                'price' => $price->toString(),
                'valid_from' => $from,
                'valid_to' => null,
                'created_by' => $author->id,
            ]);
        });
    }
}
