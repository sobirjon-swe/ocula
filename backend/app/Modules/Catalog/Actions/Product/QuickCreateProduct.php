<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions\Product;

use App\Modules\Catalog\Actions\Price\SetPrice;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Atomic quick catalog entry with an explicitly counted opening receipt. */
final class QuickCreateProduct
{
    public function __construct(private readonly SetPrice $setPrice, private readonly StockLedger $ledger) {}

    /** @param array{name:string, type:string, brand_id?:int|null, category_id?:int|null, unit?:string, sku?:string|null, barcode?:string|null, price?:string|int|null, branch_id?:int|null, opening_quantity?:int|null, cost_price?:string|int|null} $input */
    public function handle(User $author, array $input): Product
    {
        return DB::transaction(function () use ($author, $input): Product {
            $branchId = (int) ($input['branch_id'] ?? $author->branch_id);
            if (isset($input['opening_quantity']) && ! $author->canAccessAllBranches() && ! in_array($branchId, $author->accessibleBranchIds(), true)) {
                throw ValidationException::withMessages(['branch_id' => __('core::shift.foreign_branch')]);
            }
            $product = Product::create([
                ...Arr::only($input, ['name', 'type', 'brand_id', 'category_id', 'unit']),
                'status' => ProductStatus::Pending,
                'quick_created' => true,
                'created_by' => $author->id,
            ]);
            $variant = $product->variants()->create([
                'sku' => $input['sku'] ?? 'QUICK-'.Str::upper((string) Str::ulid()),
                'barcode' => $input['barcode'] ?? null,
            ]);
            if (isset($input['price'])) {
                $this->setPrice->handle($author, $variant, Money::of((string) $input['price']), $branchId ?: null);
            }

            if (isset($input['opening_quantity'])) {
                if (! isset($input['cost_price'])) {
                    throw ValidationException::withMessages(['cost_price' => __('validation.required', ['attribute' => 'cost_price'])]);
                }
                $location = Location::query()->where('branch_id', $branchId)->where('type', LocationType::Warehouse)->firstOrFail();
                $this->ledger->receive($author, $location, $variant->id, (int) $input['opening_quantity'], Money::of((string) $input['cost_price']), MovementType::Adjustment, $product, 'Quick-create: physically counted opening stock');
            }

            return $product->load(['brand', 'category', 'variants']);
        });
    }
}
