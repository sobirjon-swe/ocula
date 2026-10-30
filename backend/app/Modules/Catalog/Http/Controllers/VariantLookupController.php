<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Http\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VariantLookupController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        $term = trim($request->string('search')->toString());
        $branchId = $request->integer('branch_id') ?: $this->currentUser($request)->branch_id;
        $products = Product::query()->search($term)->select('products.id');
        $variants = ProductVariant::query()->with('product')->where('is_active', true)
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true)->whereNull('merged_into_id'))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('sku', 'ilike', '%'.$term.'%')->orWhere('barcode', $term)->orWhereIn('product_id', $products)))
            ->orderBy('id')->paginate($this->perPage($request));
        $ids = $variants->getCollection()->pluck('id')->all();
        // POS explicitly needs all-branch counts, not costs or private documents (§10).
        $branches = DB::table('branches')->where('is_active', true)->orderBy('code')->get(['id', 'code']);
        $balances = DB::table('stock_balances')->join('branches', 'branches.id', '=', 'stock_balances.branch_id')
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')->where('locations.type', 'warehouse')
            ->whereIn('variant_id', $ids)->selectRaw('variant_id, stock_balances.branch_id, branches.code, SUM(quantity)::int AS quantity')
            ->groupBy('variant_id', 'stock_balances.branch_id', 'branches.code')->orderBy('branches.code')->get()->groupBy('variant_id');
        $variants->through(function (ProductVariant $variant) use ($request, $branchId, $balances, $branches): array {
            return [...(new ProductVariantResource($variant))->resolve($request),
                'current_price' => Price::resolveFor($variant->id, $branchId)?->price->toString(),
                'branch_balances' => $branches->map(static fn ($branch): array => ['variant_id' => $variant->id, 'branch_id' => $branch->id, 'code' => $branch->code,
                    'quantity' => (int) (($balances->get($variant->id) ?? collect())->firstWhere('branch_id', $branch->id)->quantity ?? 0)])->all(),
            ];
        });

        return response()->json($variants);
    }
}
