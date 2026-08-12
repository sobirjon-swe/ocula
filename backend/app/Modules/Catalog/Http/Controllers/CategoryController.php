<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\Category\CategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Kategoriyalar — PERMISSIONS.md §2 (`catalog.category.manage`).
 *
 * Ro'yxat sahifalanmaydi: kategoriyalar oz va UI ularni daraxt qilib
 * ko'rsatadi, sahifalash daraxtni buzardi.
 */
final class CategoryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'sort')
            ->defaultSort('sort', 'name')
            ->when(
                ! $request->has('filter'),
                // Filtrsiz so'rov — ildizdan daraxt.
                fn ($query) => $query->whereNull('parent_id')->with('children'),
            )
            ->withCount('products')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return ApiResponse::created((new CategoryResource($category))->resolve($request));
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return new CategoryResource($category->load('children')->loadCount('products'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Tovari yoki ost-kategoriyasi bor kategoriya o'chirilmaydi.
     *
     * Migratsiyada `cascadeOnDelete` turibdi — u ost-kategoriyalarni
     * jimgina olib tashlardi, shuning uchun bu yerda ataylab to'siladi.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('catalog::category.in_use'),
            ]);
        }

        $category->delete();

        return ApiResponse::noContent();
    }
}
