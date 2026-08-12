<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\Product\ApproveProduct;
use App\Modules\Catalog\Actions\Product\MergeProducts;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Http\Requests\Product\ApproveProductRequest;
use App\Modules\Catalog\Http\Requests\Product\MergeProductRequest;
use App\Modules\Catalog\Http\Requests\Product\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\Product\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Katalog — PROJECT.md §6.2, 7.13, 7.17.
 *
 * Ikki yaratish yo'li:
 * - `store` (`catalog.product.create`) — to'liq kartochka;
 * - `quickStore` (`catalog.product.quick_create`) — sotuv paytida tez
 *   qo'shish, natija **doim** `pending`.
 *
 * Har ikkalasida ham `status` so'rovdan olinmaydi (`StoreProductRequest`).
 */
final class ProductController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        // Fuzzy qidiruv `filter[]` dan tashqarida — u oddiy tenglik emas,
        // trigram o'xshashligi bo'yicha **tartiblab** ham beradi (7.13,
        // ANALIZ 3.14), shuning uchun qolgan filtrlardan oldin qo'llanadi.
        $base = Product::query()->when(
            $request->filled('search'),
            fn (Builder $query) => $query->search($request->string('search')->toString()),
        );

        $products = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->with(['brand', 'category'])
            ->withCount('variants')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Direktor ekrani uchun: "Tasdiq kutilmoqda: 7 ta" (7.17).
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->pending()
            ->with(['brand', 'category', 'creator'])
            ->oldest()
            ->paginate($this->perPage($request));

        return ProductResource::collection($products);
    }

    /**
     * To'liq kartochka bilan yaratish.
     *
     * Oqim 7.17 bo'yicha: omborchi kiritgan tovar ham `pending` bo'ladi
     * va direktor tasdig'ini kutadi. Tasdiqlash huquqi bor odam (direktor)
     * kiritsa — u allaqachon tasdiqlagan hisoblanadi, keyin o'zini o'zi
     * tasdiqlab o'tirishning ma'nosi yo'q.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $status = $this->currentUser($request)->can('catalog.product.approve')
            ? ProductStatus::Approved
            : ProductStatus::Pending;

        return $this->create($request, $status, quick: false);
    }

    /**
     * Sotuv paytida tez qo'shish (7.13) — savdo to'xtamasligi kerak.
     */
    public function quickStore(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('quickCreate', Product::class);

        return $this->create($request, ProductStatus::Pending, quick: true);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load(['brand', 'category', 'variants']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return new ProductResource($product->load(['brand', 'category']));
    }

    /**
     * Tasdiqlash yoki rad etish — 7.17.
     */
    public function approve(
        ApproveProductRequest $request,
        Product $product,
        ApproveProduct $approve,
    ): ProductResource {
        $this->authorize('approve', $product);

        $updated = $approve->handle(
            $this->currentUser($request),
            $product,
            $request->boolean('approved'),
        );

        return new ProductResource($updated->load(['brand', 'category']));
    }

    /**
     * Dublikatni asosiy tovarga birlashtirish — 7.13.
     */
    public function merge(
        MergeProductRequest $request,
        Product $product,
        MergeProducts $merge,
    ): ProductResource {
        $this->authorize('merge', $product);

        $target = Product::findOrFail($request->integer('merge_into_id'));

        $merged = $merge->handle($this->currentUser($request), $product, $target);

        return new ProductResource($merged->load(['brand', 'category']));
    }

    /**
     * O'chirish faqat **varianti yo'q** tovarga (PERMISSIONS.md §2).
     *
     * Variant bor bo'lsa — demak ombor harakati yoki narx tarixi ham
     * bo'lishi mumkin; bunday tovar `is_active = false` qilinadi yoki
     * dublikat sifatida birlashtiriladi.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        if ($product->variants()->exists()) {
            throw ValidationException::withMessages([
                'product' => __('catalog::product.has_variants'),
            ]);
        }

        $product->delete();

        return ApiResponse::noContent();
    }

    private function create(StoreProductRequest $request, ProductStatus $status, bool $quick): JsonResponse
    {
        $product = Product::create([
            ...$request->validated(),
            'status' => $status,
            'quick_created' => $quick,
            'created_by' => $this->currentUser($request)->id,
        ]);

        return ApiResponse::created(
            (new ProductResource($product->load(['brand', 'category'])))->resolve($request),
        );
    }
}
