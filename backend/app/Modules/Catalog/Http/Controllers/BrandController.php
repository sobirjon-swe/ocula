<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\Brand\BrandRequest;
use App\Modules\Catalog\Http\Resources\BrandResource;
use App\Modules\Catalog\Models\Brand;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Brendlar — PERMISSIONS.md §2 (`catalog.brand.manage`).
 *
 * Ro'yxatni tovar kartochkasini to'ldiruvchi har kim ko'radi,
 * o'zgartirishni faqat direktor qiladi (`BrandPolicy`).
 */
final class BrandController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Brand::class);

        $brands = QueryBuilder::for(Brand::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->withCount('products')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BrandResource::collection($brands);
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $this->authorize('create', Brand::class);

        $brand = Brand::create($request->validated());

        return ApiResponse::created((new BrandResource($brand))->resolve($request));
    }

    public function show(Brand $brand): BrandResource
    {
        $this->authorize('view', $brand);

        return new BrandResource($brand->loadCount('products'));
    }

    public function update(BrandRequest $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $brand->update($request->validated());

        return new BrandResource($brand);
    }

    /**
     * Tovari bor brend o'chirilmaydi — tovarlar brendsiz qolib ketardi
     * va katalog kesimidagi analitika buzilardi.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);

        if ($brand->products()->exists()) {
            throw ValidationException::withMessages([
                'brand' => __('catalog::brand.in_use'),
            ]);
        }

        $brand->delete();

        return ApiResponse::noContent();
    }
}
