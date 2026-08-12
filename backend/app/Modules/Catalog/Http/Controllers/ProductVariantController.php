<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\Variant\VariantRequest;
use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tovar variantlari — SCHEMA.md §2, PROJECT.md 7.2.
 *
 * Variant tovarga ichma-ich (`/products/{product}/variants`), chunki
 * u tovarsiz mavjud emas: `product_id` majburiy va o'zgarmas.
 */
final class ProductVariantController extends ApiController
{
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductVariant::class);

        $variants = $product->variants()
            ->orderBy('sph')
            ->orderBy('cyl')
            ->orderBy('id')
            ->paginate($this->perPage($request, 50));

        return ProductVariantResource::collection($variants);
    }

    public function store(VariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', ProductVariant::class);

        $variant = $product->variants()->create($request->validated());

        return ApiResponse::created((new ProductVariantResource($variant))->resolve($request));
    }

    public function show(Product $product, ProductVariant $variant): ProductVariantResource
    {
        $this->authorize('view', $variant);
        $this->assertBelongsTo($product, $variant);

        return new ProductVariantResource($variant);
    }

    public function update(
        VariantRequest $request,
        Product $product,
        ProductVariant $variant,
    ): ProductVariantResource {
        $this->authorize('update', $variant);
        $this->assertBelongsTo($product, $variant);

        $variant->update($request->validated());

        return new ProductVariantResource($variant);
    }

    /**
     * Ichma-ich route'da ikkala parametr ham mustaqil bog'lanadi —
     * `/products/1/variants/99` da 99 boshqa tovarniki bo'lishi mumkin.
     */
    private function assertBelongsTo(Product $product, ProductVariant $variant): void
    {
        abort_unless($variant->product_id === $product->id, 404);
    }
}
