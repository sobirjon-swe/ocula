<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions\Product;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockBalance;
use App\Modules\Warehouse\Services\StockLedger;
use App\Modules\Warehouse\Services\StockMutationLock;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dublikatni asosiy tovarga birlashtirish — PROJECT.md 7.13, 7.17.
 *
 * Dublikat **o'chirilmaydi**: `merged_into_id` orqali asosiyga ishora
 * qiladi va `status = rejected` bo'ladi. Uning ombor harakati va sotuv
 * tarixi joyida qoladi, analitika esa ularni asosiy tovar ostida
 * ko'rsatadi.
 *
 * Variantlar ko'chirilmaydi — variantda `product_id` bor va uni
 * o'zgartirish ombor qatlamlarining ildizini uzib qo'yardi.
 */
final class MergeProducts
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(User $author, Product $duplicate, Product $target): Product
    {
        if ($duplicate->id === $target->id) {
            throw ValidationException::withMessages([
                'merge_into_id' => __('catalog::product.merge_into_itself'),
            ]);
        }

        if ($target->merged_into_id !== null) {
            throw ValidationException::withMessages([
                'merge_into_id' => __('catalog::product.merge_into_merged'),
            ]);
        }

        return DB::transaction(function () use ($author, $duplicate, $target): Product {
            StockMutationLock::acquire();
            Product::query()->whereIn('id', [$duplicate->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            $duplicate->refresh();
            $target->refresh();
            if ($duplicate->merged_into_id !== null || $target->merged_into_id !== null || $duplicate->type !== $target->type || ! $target->is_active || $target->status === ProductStatus::Rejected) {
                throw ValidationException::withMessages(['merge_into_id' => __('catalog::product.merge_incompatible')]);
            }
            foreach ($duplicate->variants()->whereNull('merged_into_id')->lockForUpdate()->get() as $variant) {
                $match = null;
                if ($duplicate->type === ProductType::Lens && $variant->getAttribute('sph') !== null && $variant->getAttribute('cyl') !== null) {
                    $query = $target->variants()->whereNull('merged_into_id');
                    foreach (['sph', 'cyl', 'axis', 'add', 'index', 'coating'] as $field) {
                        $value = $variant->getRawOriginal($field);
                        $value === null ? $query->whereNull($field) : $query->where($field, $value);
                    }
                    $match = $query->first();
                }
                if ($match === null) {
                    // Variant identity stays stable: all old ledger and order references remain valid.
                    $variant->update(['product_id' => $target->id]);

                    continue;
                }
                foreach (StockBalance::withoutGlobalScopes()->where('variant_id', $variant->id)->where('quantity', '>', 0)->get() as $balance) {
                    $location = Location::withoutGlobalScopes()->findOrFail($balance->location_id);
                    $movement = $this->ledger->issue($author, $location, $variant->id, $balance->quantity, MovementType::Merge, $duplicate);
                    $copied = 0;
                    foreach ($movement->consumptions()->with('layer')->get() as $consumption) {
                        $layer = $consumption->layer;
                        $this->ledger->receive($author, $location, $match->id, $consumption->quantity, $consumption->unit_cost,
                            MovementType::Merge, $duplicate,
                            receivedAt: $layer->received_at, originPurchaseId: $layer->getAttribute('origin_purchase_id'));
                        $copied += $consumption->quantity;
                    }
                    if ($copied < $balance->quantity) {
                        $this->ledger->receive($author, $location, $match->id, $balance->quantity - $copied, Money::zero(),
                            MovementType::Merge, $duplicate, costIncomplete: true);
                    }
                }
                foreach ($variant->prices()->current()->get() as $price) {
                    if (Price::resolveFor($match->id, $price->branch_id) === null) {
                        Price::create(['variant_id' => $match->id, 'branch_id' => $price->branch_id, 'price' => $price->price->toString(), 'valid_from' => now(), 'created_by' => $author->id]);
                    }
                }
                $variant->update(['merged_into_id' => $match->id, 'is_active' => false]);
            }
            $duplicate->update([
                'status' => ProductStatus::Rejected,
                'is_active' => false,
            ]);

            $duplicate->forceFill([
                'merged_into_id' => $target->id,
                'approved_by' => $author->id,
                'approved_at' => now(),
            ])->save();

            return $duplicate;
        });
    }
}
