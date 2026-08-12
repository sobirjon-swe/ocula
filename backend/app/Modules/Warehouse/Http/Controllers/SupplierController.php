<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Http\Requests\Supplier\SupplierRequest;
use App\Modules\Warehouse\Http\Resources\SupplierResource;
use App\Modules\Warehouse\Models\Supplier;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Yetkazib beruvchilar — SCHEMA.md §3, PROJECT.md 7.15.
 *
 * O'chirish yo'q: kirim tarixi ularga bog'liq, faqat faolsizlantirish.
 */
final class SupplierController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->withCount('purchases')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SupplierResource::collection($suppliers);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::create($request->validated());

        return ApiResponse::created((new SupplierResource($supplier))->resolve($request));
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($supplier->loadCount('purchases'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->update(['is_active' => false]);

        return ApiResponse::noContent();
    }
}
