<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Requests\Branch\StoreBranchRequest;
use App\Modules\Core\Http\Requests\Branch\UpdateBranchRequest;
use App\Modules\Core\Http\Resources\BranchResource;
use App\Modules\Core\Models\Branch;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Filiallar CRUD — PROJECT.md §6.1, §9.
 *
 * `code` yaratilgandan keyin o'zgarmaydi: u chiqarilgan hujjat
 * raqamlarida qolib ketadi (ANALIZ 3.12).
 */
final class BranchController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        $branches = QueryBuilder::for(Branch::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('code'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('code')
            ->withCount('users')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BranchResource::collection($branches);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::create($request->validated());

        return ApiResponse::created((new BranchResource($branch))->resolve($request));
    }

    public function show(Request $request, Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->loadCount('users'));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    /**
     * Faqat **bo'sh** filial o'chiriladi (PERMISSIONS.md §1).
     *
     * Xodimi yoki smena tarixi bor filialni o'chirish hisobotlarni
     * buzadi — buning o'rniga `is_active = false` qilinadi.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->users()->exists() || $branch->shifts()->exists() || $branch->devices()->exists()) {
            throw ValidationException::withMessages([
                'branch' => __('core::branch.not_empty'),
            ]);
        }

        $branch->locations()->delete();
        $branch->delete();

        return ApiResponse::noContent();
    }
}
